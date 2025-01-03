/*********************
 * This is the fixed version of the select extension for DataTables with the fix for the issue with the select extension
 * (https://github.com/DataTables/Select/issues/51)
 * We use this instead of the yarn version until the PR (https://github.com/DataTables/Select/pull/52) is merged and released
 * /*******************/


/*! Select for DataTables 2.0.0
 * © SpryMedia Ltd - datatables.net/license/mit
 */

import jQuery from 'jquery';
import DataTable from 'datatables.net';

// Allow reassignment of the $ variable
let $ = jQuery;


// Version information for debugger
DataTable.select = {};

DataTable.select.version = '2.0.0';

DataTable.select.init = function (dt) {
	var ctx = dt.settings()[0];

	if (!DataTable.versionCheck('2')) {
		throw 'Warning: Select requires DataTables 2 or newer';
	}

	if (ctx._select) {
		return;
	}

	var savedSelected = dt.state.loaded();

	var selectAndSave = function (e, settings, data) {
		if (data === null || data.select === undefined) {
			return;
		}

		// Clear any currently selected rows, before restoring state
		// None will be selected on first initialisation
		if (dt.rows({ selected: true }).any()) {
			dt.rows().deselect();
		}
		if (data.select.rows !== undefined) {
			dt.rows(data.select.rows).select();
		}

		if (dt.columns({ selected: true }).any()) {
			dt.columns().deselect();
		}
		if (data.select.columns !== undefined) {
			dt.columns(data.select.columns).select();
		}

		if (dt.cells({ selected: true }).any()) {
			dt.cells().deselect();
		}
		if (data.select.cells !== undefined) {
			for (var i = 0; i < data.select.cells.length; i++) {
				dt.cell(data.select.cells[i].row, data.select.cells[i].column).select();
			}
		}

		dt.state.save();
	};

	dt.on('stateSaveParams', function (e, settings, data) {
		data.select = {};
		data.select.rows = dt.rows({ selected: true }).ids(true).toArray();
		data.select.columns = dt.columns({ selected: true })[0];
		data.select.cells = dt.cells({ selected: true })[0].map(function (coords) {
			return { row: dt.row(coords.row).id(true), column: coords.column };
		});
	})
		.on('stateLoadParams', selectAndSave)
		.one('init', function () {
			selectAndSave(undefined, undefined, savedSelected);
		});

	var init = ctx.oInit.select;
	var defaults = DataTable.defaults.select;
	var opts = init === undefined ? defaults : init;

	// Set defaults
	var items = 'row';
	var style = 'api';
	var blurable = false;
	var toggleable = true;
	var info = true;
	var selector = 'td, th';
	var className = 'selected';
	var headerCheckbox = true;
	var setStyle = false;

	ctx._select = {
		infoEls: []
	};

	// Initialisation customisations
	if (opts === true) {
		style = 'os';
		setStyle = true;
	}
	else if (typeof opts === 'string') {
		style = opts;
		setStyle = true;
	}
	else if ($.isPlainObject(opts)) {
		if (opts.blurable !== undefined) {
			blurable = opts.blurable;
		}

		if (opts.toggleable !== undefined) {
			toggleable = opts.toggleable;
		}

		if (opts.info !== undefined) {
			info = opts.info;
		}

		if (opts.items !== undefined) {
			items = opts.items;
		}

		if (opts.style !== undefined) {
			style = opts.style;
			setStyle = true;
		}
		else {
			style = 'os';
			setStyle = true;
		}

		if (opts.selector !== undefined) {
			selector = opts.selector;
		}

		if (opts.className !== undefined) {
			className = opts.className;
		}

		if (opts.headerCheckbox !== undefined) {
			headerCheckbox = opts.headerCheckbox;
		}
	}

	dt.select.selector(selector);
	dt.select.items(items);
	dt.select.style(style);
	dt.select.blurable(blurable);
	dt.select.toggleable(toggleable);
	dt.select.info(info);
	ctx._select.className = className;

	// If the init options haven't enabled select, but there is a selectable
	// class name, then enable
	if (!setStyle && $(dt.table().node()).hasClass('selectable')) {
		dt.select.style('os');
	}

	// Insert a checkbox into the header if needed - might need to wait
	// for init complete, or it might already be done
	if (headerCheckbox) {
		initCheckboxHeader(dt);

		dt.on('init', function () {
			initCheckboxHeader(dt);
		});
	}
};

/*

Select is a collection of API methods, event handlers, event emitters and
buttons (for the `Buttons` extension) for DataTables. It provides the following
features, with an overview of how they are implemented:

## Selection of rows, columns and cells. Whether an item is selected or not is
   stored in:

* rows: a `_select_selected` property which contains a boolean value of the
  DataTables' `aoData` object for each row
* columns: a `_select_selected` property which contains a boolean value of the
  DataTables' `aoColumns` object for each column
* cells: a `_selected_cells` property which contains an array of boolean values
  of the `aoData` object for each row. The array is the same length as the
  columns array, with each element of it representing a cell.

This method of using boolean flags allows Select to operate when nodes have not
been created for rows / cells (DataTables' defer rendering feature).

## API methods

A range of API methods are available for triggering selection and de-selection
of rows. Methods are also available to configure the selection events that can
be triggered by an end user (such as which items are to be selected). To a large
extent, these of API methods *is* Select. It is basically a collection of helper
functions that can be used to select items in a DataTable.

Configuration of select is held in the object `_select` which is attached to the
DataTables settings object on initialisation. Select being available on a table
is not optional when Select is loaded, but its default is for selection only to
be available via the API - so the end user wouldn't be able to select rows
without additional configuration.

The `_select` object contains the following properties:

```
{
	items:string       - Can be `rows`, `columns` or `cells`. Defines what item 
	                     will be selected if the user is allowed to activate row
	                     selection using the mouse.
	style:string       - Can be `none`, `single`, `multi` or `os`. Defines the
	                     interaction style when selecting items
	blurable:boolean   - If row selection can be cleared by clicking outside of
	                     the table
	toggleable:boolean - If row selection can be cancelled by repeated clicking
	                     on the row
	info:boolean       - If the selection summary should be shown in the table
	                     information elements
	infoEls:element[]  - List of HTML elements with info elements for a table
}
```

In addition to the API methods, Select also extends the DataTables selector
options for rows, columns and cells adding a `selected` option to the selector
options object, allowing the developer to select only selected items or
unselected items.

## Mouse selection of items

Clicking on items can be used to select items. This is done by a simple event
handler that will select the items using the API methods.

 */

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Local functions
 */

/**
 * Add one or more cells to the selection when shift clicking in OS selection
 * style cell selection.
 *
 * Cell range is more complicated than row and column as we want to select
 * in the visible grid rather than by index in sequence. For example, if you
 * click first in cell 1-1 and then shift click in 2-2 - cells 1-2 and 2-1
 * should also be selected (and not 1-3, 1-4. etc)
 *
 * @param  {DataTable.Api} dt   DataTable
 * @param  {object}        idx  Cell index to select to
 * @param  {object}        last Cell index to select from
 * @private
 */
function cellRange(dt, idx, last) {
	var indexes;
	var columnIndexes;
	var rowIndexes;
	var selectColumns = function (start, end) {
		if (start > end) {
			var tmp = end;
			end = start;
			start = tmp;
		}

		var record = false;
		return dt
			.columns(':visible')
			.indexes()
			.filter(function (i) {
				if (i === start) {
					record = true;
				}

				if (i === end) {
					// not else if, as start might === end
					record = false;
					return true;
				}

				return record;
			});
	};

	var selectRows = function (start, end) {
		var indexes = dt.rows({ search: 'applied' }).indexes();

		// Which comes first - might need to swap
		if (indexes.indexOf(start) > indexes.indexOf(end)) {
			var tmp = end;
			end = start;
			start = tmp;
		}

		var record = false;
		return indexes.filter(function (i) {
			if (i === start) {
				record = true;
			}

			if (i === end) {
				record = false;
				return true;
			}

			return record;
		});
	};

	if (!dt.cells({ selected: true }).any() && !last) {
		// select from the top left cell to this one
		columnIndexes = selectColumns(0, idx.column);
		rowIndexes = selectRows(0, idx.row);
	}
	else {
		// Get column indexes between old and new
		columnIndexes = selectColumns(last.column, idx.column);
		rowIndexes = selectRows(last.row, idx.row);
	}

	indexes = dt.cells(rowIndexes, columnIndexes).flatten();

	if (!dt.cells(idx, { selected: true }).any()) {
		// Select range
		dt.cells(indexes).select();
	}
	else {
		// Deselect range
		dt.cells(indexes).deselect();
	}
}

/**
 * Disable mouse selection by removing the selectors
 *
 * @param {DataTable.Api} dt DataTable to remove events from
 * @private
 */
function disableMouseSelection(dt) {
	var ctx = dt.settings()[0];
	var selector = ctx._select.selector;

	$(dt.table().container())
		.off('mousedown.dtSelect', selector)
		.off('mouseup.dtSelect', selector)
		.off('click.dtSelect', selector);

	$('body').off('click.dtSelect' + _safeId(dt.table().node()));
}

/**
 * Attach mouse listeners to the table to allow mouse selection of items
 *
 * @param {DataTable.Api} dt DataTable to remove events from
 * @private
 */
function enableMouseSelection(dt) {
	var container = $(dt.table().container());
	var ctx = dt.settings()[0];
	var selector = ctx._select.selector;
	var matchSelection;

	container
		.on('mousedown.dtSelect', selector, function (e) {
			// Disallow text selection for shift clicking on the table so multi
			// element selection doesn't look terrible!
			if (e.shiftKey || e.metaKey || e.ctrlKey) {
				container
					.css('-moz-user-select', 'none')
					.one('selectstart.dtSelect', selector, function () {
						return false;
					});
			}

			if (window.getSelection) {
				matchSelection = window.getSelection();
			}
		})
		.on('mouseup.dtSelect', selector, function () {
			// Allow text selection to occur again, Mozilla style (tested in FF
			// 35.0.1 - still required)
			container.css('-moz-user-select', '');
		})
		.on('click.dtSelect', selector, function (e) {
			var items = dt.select.items();
			var idx;

			// If text was selected (click and drag), then we shouldn't change
			// the row's selected state
			if (matchSelection) {
				var selection = window.getSelection();

				// If the element that contains the selection is not in the table, we can ignore it
				// This can happen if the developer selects text from the click event
				if (
					!selection.anchorNode ||
					$(selection.anchorNode).closest('table')[0] === dt.table().node()
				) {
					if (selection !== matchSelection) {
						return;
					}
				}
			}

			var ctx = dt.settings()[0];
			var container = dt.table().container();

			// Ignore clicks inside a sub-table
			if ($(e.target).closest('div.dt-container')[0] != container) {
				return;
			}

			var cell = dt.cell($(e.target).closest('td, th'));

			// Check the cell actually belongs to the host DataTable (so child
			// rows, etc, are ignored)
			if (!cell.any()) {
				return;
			}

			var event = $.Event('user-select.dt');
			eventTrigger(dt, event, [items, cell, e]);

			if (event.isDefaultPrevented()) {
				return;
			}

			var cellIndex = cell.index();
			if (items === 'row') {
				idx = cellIndex.row;
				typeSelect(e, dt, ctx, 'row', idx);
			}
			else if (items === 'column') {
				idx = cell.index().column;
				typeSelect(e, dt, ctx, 'column', idx);
			}
			else if (items === 'cell') {
				idx = cell.index();
				typeSelect(e, dt, ctx, 'cell', idx);
			}

			ctx._select_lastCell = cellIndex;
		});

	// Blurable
	$('body').on('click.dtSelect' + _safeId(dt.table().node()), function (e) {
		if (ctx._select.blurable) {
			// If the click was inside the DataTables container, don't blur
			if ($(e.target).parents().filter(dt.table().container()).length) {
				return;
			}

			// Ignore elements which have been removed from the DOM (i.e. paging
			// buttons)
			if ($(e.target).parents('html').length === 0) {
				return;
			}

			// Don't blur in Editor form
			if ($(e.target).parents('div.DTE').length) {
				return;
			}

			var event = $.Event('select-blur.dt');
			eventTrigger(dt, event, [e.target, e]);

			if (event.isDefaultPrevented()) {
				return;
			}

			clear(ctx, true);
		}
	});
}

/**
 * Trigger an event on a DataTable
 *
 * @param {DataTable.Api} api      DataTable to trigger events on
 * @param  {boolean}      selected true if selected, false if deselected
 * @param  {string}       type     Item type acting on
 * @param  {boolean}      any      Require that there are values before
 *     triggering
 * @private
 */
function eventTrigger(api, type, args, any) {
	if (any && !api.flatten().length) {
		return;
	}

	if (typeof type === 'string') {
		type = type + '.dt';
	}

	args.unshift(api);

	$(api.table().node()).trigger(type, args);
}

/**
 * Update the information element of the DataTable showing information about the
 * items selected. This is done by adding tags to the existing text
 *
 * @param {DataTable.Api} api DataTable to update
 * @private
 */
function info(api, node) {
	if (api.select.style() === 'api' || api.select.info() === false) {
		return;
	}

	var rows = api.rows({ selected: true }).flatten().length;
	var columns = api.columns({ selected: true }).flatten().length;
	var cells = api.cells({ selected: true }).flatten().length;

	var add = function (el, name, num) {
		el.append(
			$('<span class="select-item"/>').append(
				api.i18n(
					'select.' + name + 's',
					{ _: '%d ' + name + 's selected', 0: '', 1: '1 ' + name + ' selected' },
					num
				)
			)
		);
	};

	var el = $(node);
	var output = $('<span class="select-info"/>');

	add(output, 'row', rows);
	add(output, 'column', columns);
	add(output, 'cell', cells);

	var existing = el.children('span.select-info');

	if (existing.length) {
		existing.remove();
	}

	if (output.text() !== '') {
		el.append(output);
	}
}

/**
 * Add a checkbox to the header for checkbox columns, allowing all rows to
 * be selected, deselected or just to show the state.
 *
 * @param {*} dt API
 */
function initCheckboxHeader( dt ) {
	// Find any checkbox column(s)
	dt.columns('.dt-select').every(function () {
		var header = this.header();

		if (! $('input', header).length) {
			// If no checkbox yet, insert one
			var input = $('<input>')
				.attr({
					class: 'dt-select-checkbox',
					type: 'checkbox',
					'aria-label': dt.i18n('select.aria.headerCheckbox') || 'Select all rows'
				})
				.appendTo(header)
				.on('change', function () {
					if (this.checked) {
						dt.rows({search: 'applied'}).select();
					}
					else {
						dt.rows({selected: true}).deselect();
					}
				})
				.on('click', function (e) {
					e.stopPropagation();
				});
	
			// Update the header checkbox's state when the selection in the
			// table changes
			dt.on('draw select deselect', function (e, pass, type) {
				if (type === 'row' || ! type) {
					var count = dt.rows({selected: true}).count();
					var search = dt.rows({search: 'applied', selected: true}).count();
					var available = dt.rows({search: 'applied'}).count();

					if (search && search <= count && search === available) {
						input
							.prop('checked', true)
							.prop('indeterminate', false);
					}
					else if (search === 0 && count === 0) {
						input
							.prop('checked', false)
							.prop('indeterminate', false);
					}
					else {
						input
							.prop('checked', false)
							.prop('indeterminate', true);
					}
				}
			});
		}
	});
}

/**
 * Initialisation of a new table. Attach event handlers and callbacks to allow
 * Select to operate correctly.
 *
 * This will occur _after_ the initial DataTables initialisation, although
 * before Ajax data is rendered, if there is ajax data
 *
 * @param  {DataTable.settings} ctx Settings object to operate on
 * @private
 */
function init(ctx) {
	var api = new DataTable.Api(ctx);
	ctx._select_init = true;

	// Row callback so that classes can be added to rows and cells if the item
	// was selected before the element was created. This will happen with the
	// `deferRender` option enabled.
	//
	// This method of attaching to `aoRowCreatedCallback` is a hack until
	// DataTables has proper events for row manipulation If you are reviewing
	// this code to create your own plug-ins, please do not do this!
	ctx.aoRowCreatedCallback.push(function (row, data, index) {
			var i, ien;
			var d = ctx.aoData[index];

			// Row
			if (d._select_selected) {
				$(row).addClass(ctx._select.className);
			}

			// Cells and columns - if separated out, we would need to do two
			// loops, so it makes sense to combine them into a single one
			for (i = 0, ien = ctx.aoColumns.length; i < ien; i++) {
				if (
					ctx.aoColumns[i]._select_selected ||
					(d._selected_cells && d._selected_cells[i])
				) {
					$(d.anCells[i]).addClass(ctx._select.className);
				}
			}
		}
	);

	// On Ajax reload we want to reselect all rows which are currently selected,
	// if there is an rowId (i.e. a unique value to identify each row with)
	api.on('preXhr.dt.dtSelect', function (e, settings) {
		if (settings !== api.settings()[0]) {
			// Not triggered by our DataTable!
			return;
		}

		// note that column selection doesn't need to be cached and then
		// reselected, as they are already selected
		var rows = api
			.rows({ selected: true })
			.ids(true)
			.filter(function (d) {
				return d !== undefined;
			});

		var cells = api
			.cells({ selected: true })
			.eq(0)
			.map(function (cellIdx) {
				var id = api.row(cellIdx.row).id(true);
				return id ? { row: id, column: cellIdx.column } : undefined;
			})
			.filter(function (d) {
				return d !== undefined;
			});

		// On the next draw, reselect the currently selected items
		api.one('draw.dt.dtSelect', function () {
			api.rows(rows).select();

			// `cells` is not a cell index selector, so it needs a loop
			if (cells.any()) {
				cells.each(function (id) {
					api.cells(id.row, id.column).select();
				});
			}
		});
	});

	// Update the table information element with selected item summary
	api.on('info.dt', function (e, ctx, node) {
		// Store the info node for updating on select / deselect
		if (!ctx._select.infoEls.includes(node)) {
			ctx._select.infoEls.push(node);
		}

		info(api, node);
	});

	api.on('select.dtSelect.dt deselect.dtSelect.dt', function () {
		ctx._select.infoEls.forEach(function (el) {
			info(api, el);
		});

		api.state.save();
	});

	// Clean up and release
	api.on('destroy.dtSelect', function () {
		// Remove class directly rather than calling deselect - which would trigger events
		$(api.rows({ selected: true }).nodes()).removeClass(api.settings()[0]._select.className);

		disableMouseSelection(api);
		api.off('.dtSelect');
		$('body').off('.dtSelect' + _safeId(api.table().node()));
	});
}

/**
 * Add one or more items (rows or columns) to the selection when shift clicking
 * in OS selection style
 *
 * @param  {DataTable.Api} dt   DataTable
 * @param  {string}        type Row or column range selector
 * @param  {object}        idx  Item index to select to
 * @param  {object}        last Item index to select from
 * @private
 */
function rowColumnRange(dt, type, idx, last) {
	// Add a range of rows from the last selected row to this one
	var indexes = dt[type + 's']({ search: 'applied' }).indexes();
	var idx1 = indexes.indexOf(last);
	var idx2 = indexes.indexOf(idx);

	if (!dt[type + 's']({ selected: true }).any() && idx1 === -1) {
		// select from top to here - slightly odd, but both Windows and Mac OS
		// do this
		indexes.splice(indexes.indexOf(idx) + 1, indexes.length);
	}
	else {
		// reverse so we can shift click 'up' as well as down
		if (idx1 > idx2) {
			var tmp = idx2;
			idx2 = idx1;
			idx1 = tmp;
		}

		indexes.splice(idx2 + 1, indexes.length);
		indexes.splice(0, idx1);
	}

	if (!dt[type](idx, { selected: true }).any()) {
		// Select range
		dt[type + 's'](indexes).select();
	}
	else {
		// Deselect range - need to keep the clicked on row selected
		indexes.splice(indexes.indexOf(idx), 1);
		dt[type + 's'](indexes).deselect();
	}
}

/**
 * Clear all selected items
 *
 * @param  {DataTable.settings} ctx Settings object of the host DataTable
 * @param  {boolean} [force=false] Force the de-selection to happen, regardless
 *     of selection style
 * @private
 */
function clear(ctx, force) {
	if (force || ctx._select.style === 'single') {
		var api = new DataTable.Api(ctx);

		api.rows({ selected: true }).deselect();
		api.columns({ selected: true }).deselect();
		api.cells({ selected: true }).deselect();
	}
}

/**
 * Select items based on the current configuration for style and items.
 *
 * @param  {object}             e    Mouse event object
 * @param  {DataTables.Api}     dt   DataTable
 * @param  {DataTable.settings} ctx  Settings object of the host DataTable
 * @param  {string}             type Items to select
 * @param  {int|object}         idx  Index of the item to select
 * @private
 */
function typeSelect(e, dt, ctx, type, idx) {
	var style = dt.select.style();
	var toggleable = dt.select.toggleable();
	var isSelected = dt[type](idx, { selected: true }).any();

	if (isSelected && !toggleable) {
		return;
	}

	if (style === 'os') {
		if (e.ctrlKey || e.metaKey) {
			// Add or remove from the selection
			dt[type](idx).select(!isSelected);
		}
		else if (e.shiftKey) {
			if (type === 'cell') {
				cellRange(dt, idx, ctx._select_lastCell || null);
			}
			else {
				rowColumnRange(
					dt,
					type,
					idx,
					ctx._select_lastCell ? ctx._select_lastCell[type] : null
				);
			}
		}
		else {
			// No cmd or shift click - deselect if selected, or select
			// this row only
			var selected = dt[type + 's']({ selected: true });

			if (isSelected && selected.flatten().length === 1) {
				dt[type](idx).deselect();
			}
			else {
				selected.deselect();
				dt[type](idx).select();
			}
		}
	}
	else if (style == 'multi+shift') {
		if (e.shiftKey) {
			if (type === 'cell') {
				cellRange(dt, idx, ctx._select_lastCell || null);
			}
			else {
				rowColumnRange(
					dt,
					type,
					idx,
					ctx._select_lastCell ? ctx._select_lastCell[type] : null
				);
			}
		}
		else {
			dt[type](idx).select(!isSelected);
		}
	}
	else {
		dt[type](idx).select(!isSelected);
	}
}

function _safeId(node) {
	return node.id.replace(/[^a-zA-Z0-9\-\_]/g, '-');
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * DataTables selectors
 */

// row and column are basically identical just assigned to different properties
// and checking a different array, so we can dynamically create the functions to
// reduce the code size
$.each(
	[
		{ type: 'row', prop: 'aoData' },
		{ type: 'column', prop: 'aoColumns' }
	],
	function (i, o) {
		DataTable.ext.selector[o.type].push(function (settings, opts, indexes) {
			var selected = opts.selected;
			var data;
			var out = [];

			if (selected !== true && selected !== false) {
				return indexes;
			}

			for (var i = 0, ien = indexes.length; i < ien; i++) {
				data = settings[o.prop][indexes[i]];

				if (
					data && (
						(selected === true && data._select_selected === true) ||
						(selected === false && !data._select_selected)
					)
				) {
					out.push(indexes[i]);
				}
			}

			return out;
		});
	}
);

DataTable.ext.selector.cell.push(function (settings, opts, cells) {
	var selected = opts.selected;
	var rowData;
	var out = [];

	if (selected === undefined) {
		return cells;
	}

	for (var i = 0, ien = cells.length; i < ien; i++) {
		rowData = settings.aoData[cells[i].row];

		if (
			rowData && (
				(selected === true &&
					rowData._selected_cells &&
					rowData._selected_cells[cells[i].column] === true) ||
				(selected === false &&
					(!rowData._selected_cells || !rowData._selected_cells[cells[i].column]))
			)
		) {
			out.push(cells[i]);
		}
	}

	return out;
});

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * DataTables API
 *
 * For complete documentation, please refer to the docs/api directory or the
 * DataTables site
 */

// Local variables to improve compression
var apiRegister = DataTable.Api.register;
var apiRegisterPlural = DataTable.Api.registerPlural;

apiRegister('select()', function () {
	return this.iterator('table', function (ctx) {
		DataTable.select.init(new DataTable.Api(ctx));
	});
});

apiRegister('select.blurable()', function (flag) {
	if (flag === undefined) {
		return this.context[0]._select.blurable;
	}

	return this.iterator('table', function (ctx) {
		ctx._select.blurable = flag;
	});
});

apiRegister('select.toggleable()', function (flag) {
	if (flag === undefined) {
		return this.context[0]._select.toggleable;
	}

	return this.iterator('table', function (ctx) {
		ctx._select.toggleable = flag;
	});
});

apiRegister('select.info()', function (flag) {
	if (flag === undefined) {
		return this.context[0]._select.info;
	}

	return this.iterator('table', function (ctx) {
		ctx._select.info = flag;
	});
});

apiRegister('select.items()', function (items) {
	if (items === undefined) {
		return this.context[0]._select.items;
	}

	return this.iterator('table', function (ctx) {
		ctx._select.items = items;

		eventTrigger(new DataTable.Api(ctx), 'selectItems', [items]);
	});
});

// Takes effect from the _next_ selection. None disables future selection, but
// does not clear the current selection. Use the `deselect` methods for that
apiRegister('select.style()', function (style) {
	if (style === undefined) {
		return this.context[0]._select.style;
	}

	return this.iterator('table', function (ctx) {
		if (!ctx._select) {
			DataTable.select.init(new DataTable.Api(ctx));
		}

		if (!ctx._select_init) {
			init(ctx);
		}

		ctx._select.style = style;

		// Add / remove mouse event handlers. They aren't required when only
		// API selection is available
		var dt = new DataTable.Api(ctx);
		disableMouseSelection(dt);

		if (style !== 'api') {
			enableMouseSelection(dt);
		}

		eventTrigger(new DataTable.Api(ctx), 'selectStyle', [style]);
	});
});

apiRegister('select.selector()', function (selector) {
	if (selector === undefined) {
		return this.context[0]._select.selector;
	}

	return this.iterator('table', function (ctx) {
		disableMouseSelection(new DataTable.Api(ctx));

		ctx._select.selector = selector;

		if (ctx._select.style !== 'api') {
			enableMouseSelection(new DataTable.Api(ctx));
		}
	});
});

apiRegister('select.last()', function (set) {
	let ctx = this.context[0];

	if (set) {
		ctx._select_lastCell = set;
		return this;
	}

	return ctx._select_lastCell;
});

apiRegisterPlural('rows().select()', 'row().select()', function (select) {
	var api = this;

	if (select === false) {
		return this.deselect();
	}

	this.iterator('row', function (ctx, idx) {
		clear(ctx);

		// There is a good amount of knowledge of DataTables internals in
		// this function. It _could_ be done without that, but it would hurt
		// performance (or DT would need new APIs for this work)
		var dtData = ctx.aoData[idx];
		var dtColumns = ctx.aoColumns;

		$(dtData.nTr).addClass(ctx._select.className);
		dtData._select_selected = true;

		for (var i=0 ; i<dtColumns.length ; i++) {
			var col = dtColumns[i];

			// Regenerate the column type if not present
			if (col.sType === null) {
				api.columns().types()
			}

			if (col.sType === 'select-checkbox') {
				// Make sure the checkbox shows the right state
				$('input.dt-select-checkbox', dtData.anCells[i]).prop('checked', true);

				// Invalidate the sort data for this column, if not already done
				if (dtData._aSortData !== null) {
					dtData._aSortData[i] = null;
				}
			}
		}
	});

	this.iterator('table', function (ctx, i) {
		eventTrigger(api, 'select', ['row', api[i]], true);
	});

	return this;
});

apiRegister('row().selected()', function () {
	var ctx = this.context[0];

	if (ctx && this.length && ctx.aoData[this[0]] && ctx.aoData[this[0]]._select_selected) {
		return true;
	}

	return false;
});

apiRegisterPlural('columns().select()', 'column().select()', function (select) {
	var api = this;

	if (select === false) {
		return this.deselect();
	}

	this.iterator('column', function (ctx, idx) {
		clear(ctx);

		ctx.aoColumns[idx]._select_selected = true;

		var column = new DataTable.Api(ctx).column(idx);

		$(column.header()).addClass(ctx._select.className);
		$(column.footer()).addClass(ctx._select.className);

		column.nodes().to$().addClass(ctx._select.className);
	});

	this.iterator('table', function (ctx, i) {
		eventTrigger(api, 'select', ['column', api[i]], true);
	});

	return this;
});

apiRegister('column().selected()', function () {
	var ctx = this.context[0];

	if (ctx && this.length && ctx.aoColumns[this[0]] && ctx.aoColumns[this[0]]._select_selected) {
		return true;
	}

	return false;
});

apiRegisterPlural('cells().select()', 'cell().select()', function (select) {
	var api = this;

	if (select === false) {
		return this.deselect();
	}

	this.iterator('cell', function (ctx, rowIdx, colIdx) {
		clear(ctx);

		var data = ctx.aoData[rowIdx];

		if (data._selected_cells === undefined) {
			data._selected_cells = [];
		}

		data._selected_cells[colIdx] = true;

		if (data.anCells) {
			$(data.anCells[colIdx]).addClass(ctx._select.className);
		}
	});

	this.iterator('table', function (ctx, i) {
		eventTrigger(api, 'select', ['cell', api.cells(api[i]).indexes().toArray()], true);
	});

	return this;
});

apiRegister('cell().selected()', function () {
	var ctx = this.context[0];

	if (ctx && this.length) {
		var row = ctx.aoData[this[0][0].row];

		if (row && row._selected_cells && row._selected_cells[this[0][0].column]) {
			return true;
		}
	}

	return false;
});

apiRegisterPlural('rows().deselect()', 'row().deselect()', function () {
	var api = this;

	this.iterator('row', function (ctx, idx) {
		// Like the select action, this has a lot of knowledge about DT internally
		var dtData = ctx.aoData[idx];
		var dtColumns = ctx.aoColumns;

		$(dtData.nTr).removeClass(ctx._select.className);
		dtData._select_selected = false;
		ctx._select_lastCell = null;

		for (var i=0 ; i<dtColumns.length ; i++) {
			var col = dtColumns[i];

			//Regenerate the column type if not present
			if (col.sType === null) {
				api.columns().types()
			}

			if (col.sType === 'select-checkbox') {
				// Make sure the checkbox shows the right state
				$('input.dt-select-checkbox', dtData.anCells[i]).prop('checked', false);

				// Invalidate the sort data for this column
				//dtData._aSortData[i] = null;
			}
		}
	});

	this.iterator('table', function (ctx, i) {
		eventTrigger(api, 'deselect', ['row', api[i]], true);
	});

	return this;
});

apiRegisterPlural('columns().deselect()', 'column().deselect()', function () {
	var api = this;

	this.iterator('column', function (ctx, idx) {
		ctx.aoColumns[idx]._select_selected = false;

		var api = new DataTable.Api(ctx);
		var column = api.column(idx);

		$(column.header()).removeClass(ctx._select.className);
		$(column.footer()).removeClass(ctx._select.className);

		// Need to loop over each cell, rather than just using
		// `column().nodes()` as cells which are individually selected should
		// not have the `selected` class removed from them
		api.cells(null, idx)
			.indexes()
			.each(function (cellIdx) {
				var data = ctx.aoData[cellIdx.row];
				var cellSelected = data._selected_cells;

				if (data.anCells && (!cellSelected || !cellSelected[cellIdx.column])) {
					$(data.anCells[cellIdx.column]).removeClass(ctx._select.className);
				}
			});
	});

	this.iterator('table', function (ctx, i) {
		eventTrigger(api, 'deselect', ['column', api[i]], true);
	});

	return this;
});

apiRegisterPlural('cells().deselect()', 'cell().deselect()', function () {
	var api = this;

	this.iterator('cell', function (ctx, rowIdx, colIdx) {
		var data = ctx.aoData[rowIdx];

		if (data._selected_cells !== undefined) {
			data._selected_cells[colIdx] = false;
		}

		// Remove class only if the cells exist, and the cell is not column
		// selected, in which case the class should remain (since it is selected
		// in the column)
		if (data.anCells && !ctx.aoColumns[colIdx]._select_selected) {
			$(data.anCells[colIdx]).removeClass(ctx._select.className);
		}
	});

	this.iterator('table', function (ctx, i) {
		eventTrigger(api, 'deselect', ['cell', api[i]], true);
	});

	return this;
});

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Buttons
 */
function i18n(label, def) {
	return function (dt) {
		return dt.i18n('buttons.' + label, def);
	};
}

// Common events with suitable namespaces
function namespacedEvents(config) {
	var unique = config._eventNamespace;

	return 'draw.dt.DT' + unique + ' select.dt.DT' + unique + ' deselect.dt.DT' + unique;
}

function enabled(dt, config) {
	if (config.limitTo.indexOf('rows') !== -1 && dt.rows({ selected: true }).any()) {
		return true;
	}

	if (config.limitTo.indexOf('columns') !== -1 && dt.columns({ selected: true }).any()) {
		return true;
	}

	if (config.limitTo.indexOf('cells') !== -1 && dt.cells({ selected: true }).any()) {
		return true;
	}

	return false;
}

var _buttonNamespace = 0;

$.extend(DataTable.ext.buttons, {
	selected: {
		text: i18n('selected', 'Selected'),
		className: 'buttons-selected',
		limitTo: ['rows', 'columns', 'cells'],
		init: function (dt, node, config) {
			var that = this;
			config._eventNamespace = '.select' + _buttonNamespace++;

			// .DT namespace listeners are removed by DataTables automatically
			// on table destroy
			dt.on(namespacedEvents(config), function () {
				that.enable(enabled(dt, config));
			});

			this.disable();
		},
		destroy: function (dt, node, config) {
			dt.off(config._eventNamespace);
		}
	},
	selectedSingle: {
		text: i18n('selectedSingle', 'Selected single'),
		className: 'buttons-selected-single',
		init: function (dt, node, config) {
			var that = this;
			config._eventNamespace = '.select' + _buttonNamespace++;

			dt.on(namespacedEvents(config), function () {
				var count =
					dt.rows({ selected: true }).flatten().length +
					dt.columns({ selected: true }).flatten().length +
					dt.cells({ selected: true }).flatten().length;

				that.enable(count === 1);
			});

			this.disable();
		},
		destroy: function (dt, node, config) {
			dt.off(config._eventNamespace);
		}
	},
	selectAll: {
		text: i18n('selectAll', 'Select all'),
		className: 'buttons-select-all',
		action: function (e, dt, node, config) {
			var items = this.select.items();
			var mod = config.selectorModifier;
			
			if (mod) {
				if (typeof mod === 'function') {
					mod = mod.call(dt, e, dt, node, config);
				}

				this[items + 's'](mod).select();
			}
			else {
				this[items + 's']().select();
			}
		}
		// selectorModifier can be specified
	},
	selectNone: {
		text: i18n('selectNone', 'Deselect all'),
		className: 'buttons-select-none',
		action: function () {
			clear(this.settings()[0], true);
		},
		init: function (dt, node, config) {
			var that = this;
			config._eventNamespace = '.select' + _buttonNamespace++;

			dt.on(namespacedEvents(config), function () {
				var count =
					dt.rows({ selected: true }).flatten().length +
					dt.columns({ selected: true }).flatten().length +
					dt.cells({ selected: true }).flatten().length;

				that.enable(count > 0);
			});

			this.disable();
		},
		destroy: function (dt, node, config) {
			dt.off(config._eventNamespace);
		}
	},
	showSelected: {
		text: i18n('showSelected', 'Show only selected'),
		className: 'buttons-show-selected',
		action: function (e, dt) {
			if (dt.search.fixed('dt-select')) {
				// Remove existing function
				dt.search.fixed('dt-select', null);

				this.active(false);
			}
			else {
				// Use a fixed filtering function to match on selected rows
				// This needs to reference the internal aoData since that is
				// where Select stores its reference for the selected state
				var dataSrc = dt.settings()[0].aoData;

				dt.search.fixed('dt-select', function (text, data, idx) {
					// _select_selected is set by Select on the data object for the row
					return dataSrc[idx]._select_selected;
				});

				this.active(true);
			}

			dt.draw();
		}
	}
});

$.each(['Row', 'Column', 'Cell'], function (i, item) {
	var lc = item.toLowerCase();

	DataTable.ext.buttons['select' + item + 's'] = {
		text: i18n('select' + item + 's', 'Select ' + lc + 's'),
		className: 'buttons-select-' + lc + 's',
		action: function () {
			this.select.items(lc);
		},
		init: function (dt) {
			var that = this;

			dt.on('selectItems.dt.DT', function (e, ctx, items) {
				that.active(items === lc);
			});
		}
	};
});

DataTable.type('select-checkbox', {
	className: 'dt-select',
	detect: function (data) {
		// Rendering function will tell us if it is a checkbox type
		return data === 'select-checkbox' ? data : false;
	},
	order: {
		pre: function (d) {
			return d === 'X' ? -1 : 0;
		}
	}
});

$.extend(true, DataTable.defaults.oLanguage, {
	select: {
		aria: {
			rowCheckbox: 'Select row'
		}
	}
});

DataTable.render.select = function (valueProp, nameProp) {
	var valueFn = valueProp ? DataTable.util.get(valueProp) : null;
	var nameFn = nameProp ? DataTable.util.get(nameProp) : null;

	return function (data, type, row, meta) {
		var dtRow = meta.settings.aoData[meta.row];
		var selected = dtRow._select_selected;
		var ariaLabel = meta.settings.oLanguage.select.aria.rowCheckbox;

		if (type === 'display') {
			return $('<input>')
				.attr({
					'aria-label': ariaLabel,
					class: 'dt-select-checkbox',
					name: nameFn ? nameFn(row) : null,
					type: 'checkbox',
					value: valueFn ? valueFn(row) : null,
					checked: selected
				})[0];
		}
		else if (type === 'type') {
			return 'select-checkbox';
		}
		else if (type === 'filter') {
			return '';
		}

		return selected ? 'X' : '';
	}
}

// Legacy checkbox ordering
DataTable.ext.order['select-checkbox'] = function (settings, col) {
	return this.api()
		.column(col, { order: 'index' })
		.nodes()
		.map(function (td) {
			if (settings._select.items === 'row') {
				return $(td).parent().hasClass(settings._select.className);
			}
			else if (settings._select.items === 'cell') {
				return $(td).hasClass(settings._select.className);
			}
			return false;
		});
};

$.fn.DataTable.select = DataTable.select;

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Initialisation
 */

// DataTables creation - check if select has been defined in the options. Note
// this required that the table be in the document! If it isn't then something
// needs to trigger this method unfortunately. The next major release of
// DataTables will rework the events and address this.
$(document).on('preInit.dt.dtSelect', function (e, ctx) {
	if (e.namespace !== 'dt') {
		return;
	}

	DataTable.select.init(new DataTable.Api(ctx));
});


export default DataTable;
