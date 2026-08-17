// Demande une confirmation native avant une action destructive déclarée dans Twig.
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static values = {
        message: String,
    };

    submit(event) {
        // L’annulation bloque la soumission sans modifier le formulaire Symfony.
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}