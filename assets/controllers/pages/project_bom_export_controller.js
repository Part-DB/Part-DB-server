import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
    };

    async export(event) {
        event.preventDefault();

        const tableElement = this.element
            .closest('#bom-tab-pane')
            ?.querySelector('table');

        if (!tableElement) {
            throw new Error('Could not find the project BOM table.');
        }

        if (
            typeof window.jQuery === 'undefined'
            || !window.jQuery.fn.DataTable.isDataTable(tableElement)
        ) {
            throw new Error('The project BOM DataTable is not initialized.');
        }

        const dataTable = window.jQuery(tableElement).DataTable();
        const ajaxParameters = dataTable.ajax.params();

        const parameters = this.toSearchParameters(ajaxParameters);

        /*
         * Export the currently visible columns in their current display order.
         * Exclude the picture column because it has no useful CSV value.
         */
        dataTable
            .columns()
            .every(function () {
                /*
                 * column.visible() returns DataTables' configured visibility.
                 *
                 * Responsive may hide a column visually and move it into a child
                 * row, but it does not change this configured visibility state.
                 */
                if (!this.visible()) {
                    return;
                }

                const index = this.index();
                const settings = dataTable.settings()[0];
                const columnSettings = settings.aoColumns[index];
                const name = columnSettings.data;

                /*
                 * The picture column is not useful in CSV.
                 */
                if (!name || name === 'picture') {
                    return;
                }

                const heading = this.header().textContent.trim();

                parameters.append('exportColumns[]', name);
                parameters.append(
                    'exportLabels[]',
                    heading || name
                );
            });

        await this.downloadExport(parameters);
    }

    async downloadExport(parameters) {
        const response = await fetch(this.urlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: parameters.toString(),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            const errorBody = await response.text();

            console.error('BOM CSV export failed:', {
                status: response.status,
                statusText: response.statusText,
                body: errorBody,
            });

            throw new Error(
                `BOM CSV export failed with HTTP ${response.status}`
            );
        }

        const blob = await response.blob();
        const filename = this.getFilename(response)
            ?? 'project_bom.csv';

        const downloadUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = downloadUrl;
        link.download = filename;
        link.style.display = 'none';

        document.body.appendChild(link);
        link.click();
        link.remove();

        URL.revokeObjectURL(downloadUrl);
    }

    getFilename(response) {
        const disposition = response.headers.get('Content-Disposition');

        if (!disposition) {
            return null;
        }

        /*
         * Prefer RFC 5987 filename*=UTF-8''... when present.
         */
        const encodedMatch = disposition.match(
            /filename\*=UTF-8''([^;]+)/i
        );

        if (encodedMatch) {
            return decodeURIComponent(encodedMatch[1]);
        }

        const filenameMatch = disposition.match(
            /filename="?([^";]+)"?/i
        );

        return filenameMatch ? filenameMatch[1] : null;
    }

    /**
     * Convert DataTables' nested AJAX object into query-string parameters.
     */
    toSearchParameters(object) {
        const parameters = new URLSearchParams();

        const append = (key, value) => {
            if (Array.isArray(value)) {
                value.forEach((item, index) => {
                    append(`${key}[${index}]`, item);
                });

                return;
            }

            if (value !== null && typeof value === 'object') {
                Object.entries(value).forEach(([childKey, childValue]) => {
                    const fullKey = key
                        ? `${key}[${childKey}]`
                        : childKey;

                    append(fullKey, childValue);
                });

                return;
            }

            parameters.append(key, String(value ?? ''));
        };

        Object.entries(object).forEach(([key, value]) => {
            append(key, value);
        });

        return parameters;
    }
}
