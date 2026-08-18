// Demande la position du navigateur uniquement après une action explicite.
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["button", "status"];

    static values = {
        allowed: Boolean,
        disabledMessage: String,
        enableMessage: String,
        unavailableMessage: String,
        searchingMessage: String,
        errorMessage: String,
    };

    connect() {
        if (!this.allowedValue) {
            this.disableButton();
            this.showStatus(this.disabledMessageValue, true);
        }
    }

    locate() {
        // La préférence SafeCity est vérifiée avant l'appel à l'API du
        // navigateur afin de ne jamais déclencher une demande non autorisée.
        if (!this.allowedValue) {
            this.showStatus(this.enableMessageValue, true);
            return;
        }

        if (!("geolocation" in navigator)) {
            this.showStatus(this.unavailableMessageValue, true);
            return;
        }

        this.disableButton();
        this.showStatus(this.searchingMessageValue);

        navigator.geolocation.getCurrentPosition(
            ({ coords }) => this.reloadWithCoordinates(coords),
            () => {
                this.enableButton();
                this.showStatus(this.errorMessageValue, true);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 300000,
            },
        );
    }

    reloadWithCoordinates(coords) {
        // Les paramètres de recherche et de filtre présents dans l'URL sont
        // conservés pendant le rechargement des résultats classés par distance.
        const url = new URL(window.location.href);
        url.searchParams.set("latitude", coords.latitude.toFixed(7));
        url.searchParams.set("longitude", coords.longitude.toFixed(7));
        window.location.assign(url.toString());
    }

    disableButton() {
        this.buttonTarget.disabled = true;
        this.buttonTarget.setAttribute("aria-disabled", "true");
    }

    enableButton() {
        this.buttonTarget.disabled = false;
        this.buttonTarget.removeAttribute("aria-disabled");
    }

    showStatus(message, isError = false) {
        this.statusTarget.textContent = message;
        this.statusTarget.classList.toggle("text-red-600", isError);
        this.statusTarget.classList.toggle("text-slate-500", !isError);
    }
}


