<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\Attachments;

use const DIRECTORY_SEPARATOR;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This service converts the relative pathes for attachments saved in database (like %MEDIA%/img.jpg) to real pathes
 * an vice versa.
 * @see \App\Tests\Services\Attachments\AttachmentPathResolverTest
 */
class AttachmentPathResolver
{
    protected string $media_path;
    protected string $footprints_path;
    protected ?string $models_path;
    protected ?string $secure_path;

    protected array $placeholders = ['%MEDIA%', '%BASE%/data/media', '%FOOTPRINTS%', '%FOOTPRINTS_3D%', '%SECURE%'];
    protected array $pathes;
    protected array $placeholders_regex;
    protected array $pathes_regex;

    /**
     * AttachmentPathResolver constructor.
     *
     * @param string      $project_dir     the kernel that should be used to resolve the project dir
     * @param string      $media_path      the path where uploaded attachments should be stored
     * @param string|null $footprints_path The path where builtin attachments are stored.
     *                                     Set to null if this ressource should be disabled.
     * @param string|null $models_path     set to null if this ressource should be disabled
     */
    public function __construct(protected string $project_dir, string $media_path, string $secure_path, ?string $footprints_path, ?string $models_path)
    {
        //Determine the path for our resources
        $this->media_path = $this->parameterToAbsolutePath($media_path) ?? throw new \InvalidArgumentException('The media path must be set and valid!');
        $this->secure_path = $this->parameterToAbsolutePath($secure_path) ?? throw new \InvalidArgumentException('The secure path must be set and valid!');
        $this->footprints_path = $this->parameterToAbsolutePath($footprints_path) ;
        $this->models_path = $this->parameterToAbsolutePath($models_path);
        $this->pathes = [$this->media_path, $this->media_path, $this->footprints_path, $this->models_path, $this->secure_path];

        //Remove all disabled placeholders
        foreach ($this->pathes as $key => $path) {
            if (null === $path) {
                unset($this->placeholders[$key], $this->pathes[$key]);
            }
        }

        //Create the regex arrays
        $this->placeholders_regex = $this->arrayToRegexArray($this->placeholders);
        $this->pathes_regex = $this->arrayToRegexArray($this->pathes);
    }

    /**
     * Converts a path passed by parameter from services.yaml (which can be an absolute path or relative to project dir)
     * to an absolute path. When a relative path is passed, the directory must exist or null is returned.
     * Returns an absolute path with "/" no matter, what system is used.
     *
     * @internal
     *
     * @param string|null $param_path The parameter value that should be converted to a absolute path
     */
    public function parameterToAbsolutePath(?string $param_path): ?string
    {
        if (null === $param_path) {
            return null;
        }

        $fs = new Filesystem();
        //If current string is already an absolute path, then we have nothing to do
        if ($fs->isAbsolutePath($param_path)) {
            $tmp = realpath($param_path);
            //Disable ressource if path is not existing
            if (false === $tmp) {
                return null;
            }

            return $tmp;
        }

        //Otherwise prepend the project path
        $tmp = realpath($this->project_dir.DIRECTORY_SEPARATOR.$param_path);

        //If path does not exist then disable the placeholder
        if (false === $tmp) {
            return null;
        }

        //Normalize file path (use / instead of \)
        return str_replace('\\', '/', $tmp);
    }

    /**
     * Converts an relative placeholder filepath (with %MEDIA% or older %BASE%) to an absolute filepath on disk.
     * The directory separator is always /. Relative pathes are not realy possible (.. is striped).
     *
     * @param string $placeholder_path the filepath with placeholder for which the real path should be determined
     *
     * @return string|null The absolute real path of the file, or null if the placeholder path is invalid
     */
    public function placeholderToRealPath(string $placeholder_path): ?string
    {
        //The new attachments use %MEDIA% as placeholders, which is the directory set in media_directory
        //Older path entries are given via %BASE% which was the project root

        $count = 0;

        //When path is a footprint we have to first run the string through our lecagy german mapping functions
        if (str_contains($placeholder_path, '%FOOTPRINTS%')) {
            $placeholder_path = $this->convertOldFootprintPath($placeholder_path);
        }

        $placeholder_path = preg_replace($this->placeholders_regex, $this->pathes, $placeholder_path, -1, $count);

        //A valid placeholder can have only one
        if (1 !== $count) {
            return null;
        }

        //If we have now have a placeholder left, the string is invalid:
        if (preg_match('#%\w+%#', (string) $placeholder_path)) {
            return null;
        }

        //Path is invalid if path is directory traversal
        if (str_contains((string) $placeholder_path, '..')) {
            return null;
        }

        //Normalize path and remove .. (to prevent directory traversal attack)
        return str_replace(['\\'], ['/'], $placeholder_path);
    }

    /**
     * Converts an real absolute filepath to a placeholder version.
     *
     * @param string $real_path   the absolute path, for which the placeholder version should be generated
     * @param bool   $old_version By default the %MEDIA% placeholder is used, which is directly replaced with the
     *                            media directory. If set to true, the old version with %BASE% will be used, which is the project directory.
     *
     * @return string The placeholder version of the filepath
     */
    public function realPathToPlaceholder(string $real_path, bool $old_version = false): ?string
    {
        $count = 0;

        //Normalize path
        $real_path = str_replace('\\', '/', $real_path);

        if ($old_version) {
            //We need to remove the %MEDIA% placeholder (element 0)
            $pathes = $this->pathes_regex;
            $placeholders = $this->placeholders;
            unset($pathes[0], $placeholders[0]);
            $real_path = preg_replace($pathes, $placeholders, $real_path, -1, $count);
        } else {
            $real_path = preg_replace($this->pathes_regex, $this->placeholders, $real_path, -1, $count);
        }

        if (1 !== $count) {
            return null;
        }

        //If the new string does not begin with a placeholder, it is invalid
        if (!preg_match('#^%\w+%#', (string) $real_path)) {
            return null;
        }

        return $real_path;
    }

    /**
     *  The path where uploaded attachments is stored.
     *
     * @return string the absolute path to the media folder
     */
    public function getMediaPath(): string
    {
        return $this->media_path;
    }

    /**
     *  The path where secured attachments are stored. Must not be located in public/ folder, so it can only be accessed
     *  via the attachment controller.
     *
     * @return string the absolute path to the secure path
     */
    public function getSecurePath(): string
    {
        return $this->secure_path;
    }

    /**
     * The string where the builtin footprints are stored.
     *
     * @return string|null The absolute path to the footprints' folder. Null if built footprints were disabled.
     */
    public function getFootprintsPath(): ?string
    {
        return $this->footprints_path;
    }

    /**
     * The string where the builtin 3D models are stored.
     *
     * @return string|null The absolute path to the models' folder. Null if builtin models were disabled.
     */
    public function getModelsPath(): ?string
    {
        return $this->models_path;
    }

    /**
     * Create an array usable for preg_replace out of an array of placeholders or pathes.
     * Slashes and other chars become escaped.
     * For example: '%TEST%' becomes '/^%TEST%/'.
     */
    protected function arrayToRegexArray(array $array): array
    {
        $ret = [];

        foreach ($array as $item) {
            $item = str_replace(['\\'], ['/'], (string) $item);
            $ret[] = '/'.preg_quote($item, '/').'/';
        }

        return $ret;
    }

    private const OLD_FOOTPINT_PATH_REPLACEMENT = [
        'Aktiv' => 'Active',
        'Bedrahtet' => 'THT',
        'Dioden' => 'Diodes',
        'Gleichrichter' => 'Rectifier',
        'GLEICHRICHTER' => 'RECTIFIER',
        'Oszillatoren' => 'Oscillator',
        'Keramikresonatoren_SMD' => 'CeramicResonator_SMD',
        'Quarze_bedrahtet' => 'Crystals_THT',
        'QUARZ' => 'CRYSTAL',
        'Quarze_SMD' => 'Crystals_SMD',
        'Quarzoszillatoren_bedrahtet' =>  'CrystalOscillator_THT',
        'QUARZOSZILLATOR' => 'CRYSTAL_OSCILLATOR',
        'Quarzoszillatoren_SMD' => 'CrystalOscillator_SMD',
        'Schaltregler' => 'SwitchingRegulator',
        'SCHALTREGLER' => 'SWITCHING_REGULATOR',
        'Akustik' => 'Acoustics',
        'Elektromechanik' => 'Electromechanics',
        'Drahtbruecken' => 'WireJumpers',
        'DRAHTBRUECKE' => 'WIREJUMPER',
        'IC-Sockel' => 'IC-Socket',
        'SOCKEL' => 'SOCKET',
        'Kuehlkoerper' => 'Heatsinks',
        'KUEHLKOERPER' => 'HEATSINK',
        'Relais' => 'Relays',
        'RELAIS' => 'RELAY',
        'Schalter_Taster' => 'Switches_Buttons',
        'Drehschalter' => 'RotarySwitches',
        'DREHSCHALTER' => 'ROTARY_SWITCH',
        'Drucktaster' => 'Button',
        'TASTER' => 'BUTTON',
        'Kippschalter' => 'ToggleSwitch',
        'KIPPSCHALTER' => 'TOGGLE_SWITCH',
        'Gewinde' => 'Threaded',
        'abgewinkelt' => 'angled',
        'hochkant' => 'vertical',
        'stehend' => 'vertical',
        'liegend' => 'horizontal',
        '_WECHSLER' => '',
        'Schiebeschalter' => 'SlideSwitch',
        'SCHIEBESCHALTER' => 'SLIDE_SWITCH',
        'Sicherungshalter' => 'Fuseholder',
        'SICHERUNGSHALTER_Laengs' => 'FUSEHOLDER_Lenghtway',
        'SICHERUNGSHALTER_Quer' => 'FUSEHOLDER_Across',
        'Speicherkartenslots' => 'MemoryCardSlots',
        'KARTENSLOT' => 'CARD_SLOT',
        'SD-Karte' => 'SD_Card',
        'Rot' => 'Red',
        'Schwarz' => 'Black',
        'Verbinder' => 'Connectors',
        'BUCHSE' => 'SOCKET',
        'Buchsenleisten' => 'SocketStrips',
        'Reihig' => 'Row',
        'gerade' => 'straight',
        'flach' => 'flat',
        'praezisions' => 'precision',
        'praezision' => 'precision',
        'BUCHSENLEISTE' => 'SOCKET_STRIP',
        'GERADE' => 'STRAIGHT',
        'FLACH' => 'FLAT',
        'PRAEZISION' => 'PRECISION',
        'ABGEWINKELT' => 'ANGLED',
        'Federkraftklemmen' => 'SpringClamps',
        'SCHRAUBKLEMME' => 'SCREW_CLAMP',
        'KLEMME' => 'CLAMP',
        'VERBINDER' => 'CONNECTOR',
        'Loetoesen' => 'SolderingPads',
        'LOETOESE' => 'SOLDERING_PAD',
        'Rundsteckverbinder' => 'DINConnectors',
        'Schraubklemmen' => 'ScrewClamps',
        'Sonstiges' => 'Miscellaneous',
        'Stiftleisten' => 'PinHeaders',
        'STIFTLEISTE' => 'PIN_HEADER',
        'mit_Rahmen' => 'with_frame',
        'RAHMEN' => 'FRAME',
        'Maennlich' => 'Male',
        'Platinenmontage' => 'PCBMount',
        'PLATINENMONTAGE' => 'PCB_MOUNT',
        'Weiblich' => 'Female',
        'Optik' => 'Optics',
        'BLAU' => 'BLUE',
        'GELD' => 'YELLOW',
        'GRUEN' => 'GREEN',
        'ROT' => 'RED',
        'eckig' => 'square',
        'Passiv' => 'Passive',
        'EMV' => 'EMC',
        'Induktivitaeten' => 'Inductors',
        'SPULE' => 'COIL',
        'Kondensatoren' => 'Capacitors',
        'ELKO' => 'Electrolyte',
        'Elektrolyt' => 'Electrolyte',
        'Folie' => 'Film',
        'FOLIENKONDENSATOR' => 'FILM_CAPACITOR',
        'Keramik' => 'Ceramic',
        'KERKO' => 'Ceramic',
        'Tantal' => 'Tantalum',
        'TANTAL' => 'TANTALUM',
        'Trimmkondensatoren' => 'TrimmerCapacitors',
        'TRIMMKONDENSATOR' => 'TRIMMER_CAPACITOR',
        'KONDENSATOR' => 'CAPACITOR',
        'Transformatoren' => 'Transformers',
        'TRAFO' => 'TRANSFORMER',
        'Widerstaende' => 'Resistors',
        'WIDERSTAND' => 'RESISTOR',
        'Dickschicht' => 'ThickFilm',
        'DICKSCHICHT' => 'THICK_FILM',
        'KERAMIK' => 'CERAMIC',
        'Kohleschicht' => 'Carbon',
        'KOHLE' => 'CARBON',
        'Sonstige' => 'Miscellaneous', //Have to be last (after "Sonstiges")
    ];

    public function convertOldFootprintPath(string $old_path): string
    {
        //Only do the conversion if it contains a german string (meaning it has one of the four former base folders in its path)
        if (!preg_match('/%FOOTPRINTS%\/(Passiv|Aktiv|Akustik|Elektromechanik|Optik)\//', $old_path)) {
            return $old_path;
        }

        return strtr($old_path, self::OLD_FOOTPINT_PATH_REPLACEMENT);
    }
}
