import { Controller } from "@hotwired/stimulus";

export default class extends Controller {

    static targets = ["input", "slot"];

    connect() {

        this.slotTargets.forEach((slot, index) => {

            slot.addEventListener("click", (event) => {

                if (event.target.classList.contains("remove")) {
                    return;
                }

                this.inputTargets[index].click();

            });

        });

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

            image.classList.remove("d-none");
            placeholder.classList.add("d-none");
            remove.classList.remove("d-none");

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
        image.classList.add("d-none");

        placeholder.classList.remove("d-none");
        remove.classList.add("d-none");

    }

}