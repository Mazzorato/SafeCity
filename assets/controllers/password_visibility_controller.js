// Affiche ou masque le mot de passe sans modifier la valeur saisie.
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["input", "icon"];

    toggle(event) {
        event.preventDefault();

        const passwordIsVisible = this.inputTarget.type === "text";
        this.inputTarget.type = passwordIsVisible ? "password" : "text";
        this.iconTarget.textContent = passwordIsVisible ? "◉" : "◎";
        event.currentTarget.setAttribute("aria-pressed", String(!passwordIsVisible));
    }
}
