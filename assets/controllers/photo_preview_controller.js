// Prévisualise et retire les photos sélectionnées avant l’envoi du formulaire.
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {

    static targets = ["input", "slot"];

    select(event) {
        if (event.target instanceof HTMLInputElement || event.target.closest(".remove")) {
            return;
        }

        event.preventDefault();
        const index = this.slotTargets.indexOf(event.currentTarget);
        const input = this.inputTargets[index];
        if (input) {
            // Un clic sur une case reste toujours un choix depuis la galerie.
            input.removeAttribute("capture");
            input.click();
        }
    }

    capture(event) {
        event.preventDefault();

        // La prise de vue utilise la première case encore libre afin de
        // conserver la limite des trois champs du formulaire Symfony.
        const input = this.inputTargets.find((candidate) => !candidate.files?.length);
        if (!input) {
            return;
        }

        input.setAttribute("capture", "environment");
        input.click();

        // L’attribut ne doit pas transformer les clics suivants en accès caméra.
        window.setTimeout(() => input.removeAttribute("capture"), 0);
    }

    preview(event) {
        const input = event.target;
        const index = this.inputTargets.indexOf(input);
        const file = input.files[0];

        if (!file) {
            return;
        }

        const reader = new FileReader();

        reader.onload = (e) => {
            const slot = this.slotTargets[index];
            const image = slot.querySelector(".preview-image");
            const placeholder = slot.querySelector(".placeholder");
            const remove = slot.querySelector(".remove");

            image.src = e.target.result;

            image.classList.remove("hidden");
            placeholder.classList.add("hidden");
            remove.classList.remove("hidden");
        };

        reader.readAsDataURL(file);
    }

    remove(event) {
        event.preventDefault();
        event.stopPropagation();

        const slot = event.target.closest(".slot");
        const index = this.slotTargets.indexOf(slot);
        const input = this.inputTargets[index];

        input.value = "";

        const image = slot.querySelector(".preview-image");
        const placeholder = slot.querySelector(".placeholder");
        const remove = slot.querySelector(".remove");

        image.src = "";
        image.classList.add("hidden");

        placeholder.classList.remove("hidden");
        remove.classList.add("hidden");
    }
}


