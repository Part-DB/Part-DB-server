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

    'Location ID': 'Lagerort ID',
    'Name': 'Name',
    'Full path': 'Vollständiger Pfad',
    'Parent name': 'Name des Übergeordneten Elements',
    'Parent full path': 'Ganzer Pfad des Übergeordneten Elements',

    'Username': 'Benutzername',
    'Username (including name)': 'Benutzername (inklusive Name)',
    'Current datetime': 'Aktuelle Datum/Zeit',
    'Current date': 'Aktuelles Datum',
    'Current time': 'Aktuelle Zeit',
    'Instance name': 'Instanzname',
    'Target type': 'Zieltyp',

} );