/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

// Make sure that the global object is defined. If not, define it.
window.CKEDITOR_TRANSLATIONS = window.CKEDITOR_TRANSLATIONS || {};

// Make sure that the dictionary for Polish translations exist.
window.CKEDITOR_TRANSLATIONS[ 'de' ] = window.CKEDITOR_TRANSLATIONS[ 'de' ] || {};
window.CKEDITOR_TRANSLATIONS[ 'de' ].dictionary =  window.CKEDITOR_TRANSLATIONS[ 'de' ].dictionary || {};

// Extend the dictionary for Polish translations with your translations:
Object.assign( window.CKEDITOR_TRANSLATIONS[ 'de' ].dictionary, {
    'Label Placeholder': 'Label Platzhalter',
    'Part': 'Bauteil',

    'Database ID': 'Datenbank ID',
    'Part name': 'Bauteilname',
    'Category': 'Kategorie',
    'Category (Full path)': 'Kategorie (Vollständiger Pfad)',
    'Manufacturer': 'Hersteller',
    'Manufacturer (Full path)': 'Hersteller (Vollständiger Pfad)',
    'Footprint': 'Footprint',
    'Footprint (Full path)': 'Footprint (Vollständiger Pfad)',
    'Mass': 'Gewicht',
    'Manufacturer Product Number (MPN)': 'Hersteller Produktnummer (MPN)',
    'Tags': 'Tags',
    'Manufacturing status': 'Herstellungsstatus',
    'Description': 'Beschreibung',
    'Description (plain text)': 'Beschreibung (Nur-Text)',
    'Comment': 'Kommentar',
    'Comment (plain text)': 'Kommentar (Nur-Text)',
    'Last modified datetime': 'Zuletzt geändert',
    'Creation datetime': 'Erstellt',

    'Lot ID': 'Lot ID',
    'Lot name': 'Lot Name',
    'Lot comment': 'Lot Kommentar',
    'Lot expiration date': 'Lot Ablaufdatum',
    'Lot amount': 'Lot Menge',
    'Storage location': 'Lagerort',
    'Storage location (Full path)': 'Lagerort (Vollständiger Pfad)',
    'Full name of the lot owner': 'Name des Besitzers des Lots',
    'Username of the lot owner': 'Benutzername des Besitzers des Lots',


    'Barcodes': 'Barcodes',
    'Content of the 1D barcodes (like Code 39)': 'Inhalt der 1D Barcodes (z.B. Code 39)',
    'Content of the 2D barcodes (QR codes)': 'Inhalt der 2D Barcodes (QR Codes)',
    'QR code linking to this element': 'QR Code verknüpft mit diesem Element',
    'Code 128 barcode linking to this element': 'Code 128 Barcode verknüpft mit diesem Element',
    'Code 39 barcode linking to this element': 'Code 39 Barcode verknüpft mit diesem Element',

    'Location ID': 'Lagerort ID',
    'Name': 'Name',
    'Full path': 'Vollständiger Pfad',
    'Parent name': 'Name des Übergeordneten Elements',
    'Parent full path': 'Ganzer Pfad des Übergeordneten Elements',
    'Full name of the location owner': 'Name des Besitzers des Lagerorts',
    'Username of the location owner': 'Benutzername des Besitzers des Lagerorts',

    'Username': 'Benutzername',
    'Username (including name)': 'Benutzername (inklusive Name)',
    'Current datetime': 'Aktuelle Datum/Zeit',
    'Current date': 'Aktuelles Datum',
    'Current time': 'Aktuelle Zeit',
    'Instance name': 'Instanzname',
    'Target type': 'Zieltyp',
    'URL of this Part-DB instance': 'URL dieser Part-DB Instanz',

} );