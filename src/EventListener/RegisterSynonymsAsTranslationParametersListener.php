<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);


namespace App\EventListener;

use App\Services\ElementTypeNameGenerator;
use App\Services\ElementTypes;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener]
readonly class RegisterSynonymsAsTranslationParametersListener
{
    private Translator $translator;

    public function __construct(
        #[Autowire(service: 'translator.default')] TranslatorInterface $translator,
        private TagAwareCacheInterface $cache,
        private ElementTypeNameGenerator $typeNameGenerator)
    {
        if (!$translator instanceof Translator) {
            throw new \RuntimeException('Translator must be an instance of Symfony\Component\Translation\Translator or this listener cannot be used.');
        }
        $this->translator = $translator;
    }

    public function getSynonymPlaceholders(string $locale): array
    {
        return $this->cache->get('partdb_synonym_placeholders' . '_' . $locale, function (ItemInterface $item) use ($locale) {
            $item->tag('synonyms');


            $placeholders = [];

            //Generate a placeholder for each element type
            foreach (ElementTypes::cases() as $elementType) {
                //Versions with capitalized first letter
                $capitalized = ucfirst($elementType->value); //We have only ASCII element type values, so this is sufficient
                $placeholders['[' . $capitalized . ']'] = $this->typeNameGenerator->typeLabel($elementType, $locale);
                $placeholders['[[' . $capitalized . ']]'] = $this->typeNameGenerator->typeLabelPlural($elementType, $locale);

                //And we have lowercase versions for both
                $placeholders['[' . $elementType->value . ']'] = mb_strtolower($this->typeNameGenerator->typeLabel($elementType, $locale));
                $placeholders['[[' . $elementType->value . ']]'] = mb_strtolower($this->typeNameGenerator->typeLabelPlural($elementType, $locale));
            }

            return $placeholders;
        });
    }

    public function __invoke(RequestEvent $event): void
    {
        //If we already added the parameters, skip adding them again
        if (isset($this->translator->getGlobalParameters()['@@partdb_synonyms_registered@@'])) {
            return;
        }

        //Register all placeholders for synonyms
        $placeholders = $this->getSynonymPlaceholders($event->getRequest()->getLocale());
        foreach ($placeholders as $key => $value) {
            $this->translator->addGlobalParameter($key, $value);
        }

        //Register the marker parameter to avoid double registration
        $this->translator->addGlobalParameter('@@partdb_synonyms_registered@@', 'registered');
    }
}
