import { Controller } from "@hotwired/stimulus";
import "../../css/components/autocomplete_bootstrap_theme.css";

export default class extends Controller {
    static targets = ["input"];
    static values = {
        partId: Number,
        partCategoryId: Number,
        partDescription: String,
        suggestions: Object,
        commonSectionHeader: String, // Dynamic header for common Prefixes
        partIncrementHeader: String, // Dynamic header for new possible part increment
        suggestUrl: String,
    };

    connect() {
        this.configureAutocomplete();
        this.watchCategoryChanges();
        this.watchDescriptionChanges();
    }

    templates = {
        commonSectionHeader({ title, html }) {
            return html`
                <section class="aa-Source">
                    <div class="aa-SourceHeader">
                        <span class="aa-SourceHeaderTitle">${title}</span>
                        <div class="aa-SourceHeaderLine"></div>
                    </div>
                </section>
            `;
        },
        partIncrementHeader({ title, html }) {
            return html`
                <section class="aa-Source">
                    <div class="aa-SourceHeader">
                        <span class="aa-SourceHeaderTitle">${title}</span>
                        <div class="aa-SourceHeaderLine"></div>
                    </div>
                </section>
            `;
        },
        list({ html }) {
            return html`
                <ul class="aa-List" role="listbox"></ul>
            `;
        },
        item({ suggestion, description, html }) {
            return html`
                <li class="aa-Item" role="option" data-suggestion="${suggestion}" aria-selected="false">
                    <div class="aa-ItemWrapper">
                        <div class="aa-ItemContent">
                            <div class="aa-ItemIcon aa-ItemIcon--noBorder">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 21c4.971 0 9-4.029 9-9s-4.029-9-9-9-9 4.029-9 9 4.029 9 9 9z"></path>
                                </svg>
                            </div>
                            <div class="aa-ItemContentBody">
                                <div class="aa-ItemContentTitle">${suggestion}</div>
                                <div class="aa-ItemContentDescription">${description}</div>
                            </div>
                        </div>
                    </div>
                </li>
            `;
        },
    };

    configureAutocomplete() {
        const inputField = this.inputTarget;
        const commonPrefixes = this.suggestionsValue.commonPrefixes || [];
        const prefixesPartIncrement = this.suggestionsValue.prefixesPartIncrement || [];
        const commonHeader = this.commonSectionHeaderValue;
        const partIncrementHeader = this.partIncrementHeaderValue;

        if (!inputField || (!commonPrefixes.length && !prefixesPartIncrement.length)) return;

        // Check whether the panel should be created at the update
        if (this.isPanelInitialized) {
            const existingPanel = inputField.parentNode.querySelector(".aa-Panel");
            if (existingPanel) {
                // Only remove the panel in the update phase

                existingPanel.remove();
            }
        }

        // Create panel
        const panel = document.createElement("div");
        panel.classList.add("aa-Panel");
        panel.style.display = "none";

        // Create panel layout
        const panelLayout = document.createElement("div");
        panelLayout.classList.add("aa-PanelLayout", "aa-Panel--scrollable");

        // Section for prefixes part increment
        if (prefixesPartIncrement.length) {
            const partIncrementSection = document.createElement("section");
            partIncrementSection.classList.add("aa-Source");

            const partIncrementHeaderHtml = this.templates.partIncrementHeader({
                title: partIncrementHeader,
                html: String.raw,
            });
            partIncrementSection.innerHTML += partIncrementHeaderHtml;

            const partIncrementList = document.createElement("ul");
            partIncrementList.classList.add("aa-List");
            partIncrementList.setAttribute("role", "listbox");

            prefixesPartIncrement.forEach((prefix) => {
                const itemHTML = this.templates.item({
                    suggestion: prefix.title,
                    description: prefix.description,
                    html: String.raw,
                });
                partIncrementList.innerHTML += itemHTML;
            });

            partIncrementSection.appendChild(partIncrementList);
            panelLayout.appendChild(partIncrementSection);
        }

        // Section for common prefixes
        if (commonPrefixes.length) {
            const commonSection = document.createElement("section");
            commonSection.classList.add("aa-Source");

            const commonSectionHeader = this.templates.commonSectionHeader({
                title: commonHeader,
                html: String.raw,
            });
            commonSection.innerHTML += commonSectionHeader;

            const commonList = document.createElement("ul");
            commonList.classList.add("aa-List");
            commonList.setAttribute("role", "listbox");

            commonPrefixes.forEach((prefix) => {
                const itemHTML = this.templates.item({
                    suggestion: prefix.title,
                    description: prefix.description,
                    html: String.raw,
                });
                commonList.innerHTML += itemHTML;
            });

            commonSection.appendChild(commonList);
            panelLayout.appendChild(commonSection);
        }

        panel.appendChild(panelLayout);
        inputField.parentNode.appendChild(panel);

        inputField.addEventListener("focus", () => {
            panel.style.display = "block";
        });

        inputField.addEventListener("blur", () => {
            setTimeout(() => {
                panel.style.display = "none";
            }, 100);
        });

        // Selection of an item
        panelLayout.addEventListener("mousedown", (event) => {
            const target = event.target.closest("li");

            if (target) {
                inputField.value = target.dataset.suggestion;
                panel.style.display = "none";
            }
        });

        this.isPanelInitialized = true;
    };

    watchCategoryChanges() {
        const categoryField = document.querySelector('[data-ipn-suggestion="categoryField"]');
        const descriptionField = document.querySelector('[data-ipn-suggestion="descriptionField"]');
        this.previousCategoryId = Number(this.partCategoryIdValue);

        if (categoryField) {
            categoryField.addEventListener("change", () => {
                const categoryId = Number(categoryField.value);
                const description = String(descriptionField?.value ?? '');

                // Check whether the category has changed compared to the previous ID
                if (categoryId !== this.previousCategoryId) {
                    this.fetchNewSuggestions(categoryId, description);
                    this.previousCategoryId = categoryId;
                }
            });
        }
    }

    watchDescriptionChanges() {
        const categoryField = document.querySelector('[data-ipn-suggestion="categoryField"]');
        const descriptionField = document.querySelector('[data-ipn-suggestion="descriptionField"]');
        this.previousDescription = String(this.partDescriptionValue);

        if (descriptionField) {
            descriptionField.addEventListener("input", () => {
                const categoryId = Number(categoryField.value);
                const description = String(descriptionField?.value ?? '');

                // Check whether the description has changed compared to the previous one
                if (description !== this.previousDescription) {
                    this.fetchNewSuggestions(categoryId, description);
                    this.previousDescription = description;
                }
            });
        }
    }

    fetchNewSuggestions(categoryId, description) {
        const baseUrl = this.suggestUrlValue;
        const partId = this.partIdValue;
        const truncatedDescription = description.length > 150 ? description.substring(0, 150) : description;
        const encodedDescription = this.base64EncodeUtf8(truncatedDescription);
        const url = `${baseUrl}?partId=${partId}&categoryId=${categoryId}` + (description !== '' ? `&description=${encodedDescription}` : '');

        fetch(url, {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Error when calling up the IPN-suggestions: ${response.status}`);
                }
                return response.json();
            })
            .then((data) => {
                this.suggestionsValue = data;
                this.configureAutocomplete();
            })
            .catch((error) => {
                console.error("Errors when loading the new IPN-suggestions:", error);
            });
    };

    base64EncodeUtf8(text) {
        const utf8Bytes = new TextEncoder().encode(text);
        return btoa(String.fromCharCode(...utf8Bytes));
    };
}
