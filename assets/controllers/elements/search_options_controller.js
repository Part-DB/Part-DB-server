import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["datasource", "partOptions", "assemblyOptions", "projectOptions", "divider"];
    static values = {
        isSearchList: Boolean
    };

    connect() {
        // Delay update slightly to ensure all child controllers are connected and DOM is ready
        setTimeout(() => {
            this.updateVisibility();
        }, 1000);
    }

    onDatasourceChange() {
        this.updateVisibility();
    }

    updateVisibility() {
        if (!this.hasDatasourceTarget) return;

        const datasource = this.datasourceTarget.value;
        const isSearchList = this.isSearchListValue;
        const isPart = (datasource === "parts");
        const isAssembly = (datasource === "assemblies");
        const isProject = (datasource === "projects");

        if (this.hasPartOptionsTarget) {
            this.toggleOptions(this.partOptionsTarget, isPart, isSearchList);
        }

        if (this.hasAssemblyOptionsTarget) {
            this.toggleOptions(this.assemblyOptionsTarget, isAssembly, isSearchList);
        }

        if (this.hasProjectOptionsTarget) {
            this.toggleOptions(this.projectOptionsTarget, isProject, isSearchList);
        }

        if (this.hasDividerTarget) {
            this.dividerTarget.classList.toggle("d-none", !isPart && !isAssembly && !isProject);
        }
    }

    toggleOptions(container, show, isSearchList) {
        const wasHidden = container.classList.contains("d-none");
        container.classList.toggle("d-none", !show);

        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        if (!show) {
            // Deselect checkboxes if not in correct mode
            checkboxes.forEach(checkbox => {
                // Store current state to restore it later if the user switches back
                if (checkbox.checked) {
                    checkbox.dataset.previousState = "true";
                    checkbox.checked = false;
                    // Trigger a change event to update sessionStorage via the sessionStorage_checkbox controller
                    // We use a CustomEvent to pass the skipStorage flag
                    checkbox.dispatchEvent(new CustomEvent('change', { bubbles: true, detail: { skipStorage: true } }));
                }
            });
        } else if (wasHidden) {
            // Restore state when switching back
            checkboxes.forEach(checkbox => {
                // Restore state if NOT on search list
                // On search list, we don't restore to avoid overwriting Twig's checked state
                if (!isSearchList && checkbox.dataset.previousState === "true") {
                    checkbox.checked = true;
                    checkbox.dispatchEvent(new CustomEvent('change', { bubbles: true, detail: { skipStorage: true } }));
                }
                delete checkbox.dataset.previousState;
            });
        }
    }
}
