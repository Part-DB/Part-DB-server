import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["part", "assembly", "name"];

    connect() {
        this.updatePlaceholder();
        // Give TomSelect some time to initialize and set values
        setTimeout(() => this.updatePlaceholder(), 100);
        setTimeout(() => this.updatePlaceholder(), 500);
    }

    updatePlaceholder() {
        const partSelect = this.hasPartTarget ? this.partTarget.querySelector('select, input') : null;
        const assemblySelect = this.hasAssemblyTarget ? this.assemblyTarget.querySelector('select, input') : null;
        const nameInput = this.hasNameTarget ? this.nameTarget : null;

        if (!nameInput) return;

        let selectedName = "";

        // Helper to get name from tomselect
        const getNameFromTS = (el) => {
            if (el && el.tomselect) {
                const val = el.tomselect.getValue();
                if (val) {
                    const data = el.tomselect.options[val];
                    if (data && data.name) return data.name;
                }
            }
            // Fallback for raw select
            if (el && el.value && el.options && el.selectedIndex >= 0) {
                return el.options[el.selectedIndex].text;
            }
            return "";
        };

        selectedName = getNameFromTS(partSelect);

        if (!selectedName) {
            selectedName = getNameFromTS(assemblySelect);
        }

        if (selectedName) {
            nameInput.placeholder = selectedName;
            if (nameInput.value === "") {
                nameInput.style.opacity = "0.6";
            } else {
                nameInput.style.opacity = "1";
            }
        } else {
            nameInput.placeholder = nameInput.dataset.originalPlaceholder || "";
            nameInput.style.opacity = "1";
        }
    }

    // This method will be called via action when a change occurs
    sync(event) {
        // Handle mutual exclusion: if a part is selected, clear the assembly (and vice-versa)
        // We identify which field was changed by looking at the event target
        const changedElement = event.target;
        const partSelect = this.hasPartTarget ? this.partTarget.querySelector('select, input') : null;
        const assemblySelect = this.hasAssemblyTarget ? this.assemblyTarget.querySelector('select, input') : null;

        // If part was changed and has a value, clear assembly
        if (partSelect && (changedElement === partSelect || partSelect.contains(changedElement))) {
            const val = partSelect.tomselect ? partSelect.tomselect.getValue() : partSelect.value;
            if (val && assemblySelect) {
                if (assemblySelect.tomselect) {
                    assemblySelect.tomselect.clear(true); // true to silent event to avoid loops
                } else {
                    assemblySelect.value = "";
                }
            }
        }

        // If assembly was changed and has a value, clear part
        if (assemblySelect && (changedElement === assemblySelect || assemblySelect.contains(changedElement))) {
            const val = assemblySelect.tomselect ? assemblySelect.tomselect.getValue() : assemblySelect.value;
            if (val && partSelect) {
                if (partSelect.tomselect) {
                    partSelect.tomselect.clear(true); // true to silent event to avoid loops
                } else {
                    partSelect.value = "";
                }
            }
        }

        // Delay slightly to allow TomSelect to update its internal state if needed
        setTimeout(() => {
            this.updatePlaceholder();
        }, 100);
    }
}
