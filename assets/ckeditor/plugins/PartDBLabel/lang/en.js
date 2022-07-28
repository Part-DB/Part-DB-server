// Make sure that the global object is defined. If not, define it.
window.CKEDITOR_TRANSLATIONS = window.CKEDITOR_TRANSLATIONS || {};

// Make sure that the dictionary for Polish translations exist.
window.CKEDITOR_TRANSLATIONS[ 'en' ] = window.CKEDITOR_TRANSLATIONS[ 'en' ] || {};
window.CKEDITOR_TRANSLATIONS[ 'en' ].dictionary =  window.CKEDITOR_TRANSLATIONS[ 'en' ].dictionary || {};

// Extend the dictionary for Polish translations with your translations:
Object.assign( window.CKEDITOR_TRANSLATIONS[ 'en' ].dictionary, {
    'part_db.title': 'Insert Placeholders',
    'part_db.label': 'Placeholders',
    'section.global': 'Globals',
    'section.part': 'Part',
    'section.part_lot': 'Part lot',
    'section.storelocation': 'Storage location',
    'part.id': 'Database ID',
    'part.name': 'Part name',
    'part.category': 'Category',
    'part.category_full': 'Category (Full path)',
    'part.manufacturer': 'Manufacturer',
    'part.manufacturer_full': 'Manufacturer (Full path)',
    'part.footprint': 'Footprint',
    'part.footprint_full': 'Footprint (Full path)',
    'part.mass': 'Mass',
    'part.tags': 'Tags',
    'part.mpn': 'Manufacturer Product Number (MPN)',
    'part.status': 'Manufacturing status',
    'part.description': 'Description',
    'part.description_t': 'Description (Text)',
    'part.comment': 'Comment',
    'part.comment_t': 'Comment (Text)',
    'part.last_modified': 'Last modified datetime',
    'part.creation_date': 'Creation datetime',
    'global.username': 'Username',
    'global.username_full': 'Username (including name)',
    'global.datetime': 'Current datetime',
    'global.date': 'Current date',
    'global.time': 'Current time',
    'global.install_name': 'Instance name',
    'global.type': 'Target type',
    'lot.id': 'Lot ID',
    'lot.name': 'Lot name',
    'lot.comment': 'Lot comment',
    'lot.expiration_date': 'Expiration date',
    'lot.amount': 'Lot amount',
    'lot.location': 'Storage location',
    'lot.location_full': 'Storage location (Full path)',

    'storelocation.id': 'Location ID',
    'storelocation.name': 'Name',
    'storelocation.full_path': 'Full path',
    'storelocation.parent_name': 'Parent name',
    'storelocation.parent_full_path': 'Parent full path',
    'storelocation.comment': 'Comment',
    'storelocation.comment_t': 'Comment (Text)',
    'storelocation.last_modified': 'Last modified datetime',
    'storelocation.creation_date': 'Createion datetime',
} );