// Recherche une origine par adresse ou par position GPS pour classer les parkings.
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [
        "form",
        "address",
        "source",
        "latitude",
        "longitude",
        "button",
        "submit",
        "status",
    ];

    static values = {
        allowed: Boolean,
        origin: Boolean,
        geocodingUrl: String,
        disabledMessage: String,
        enableMessage: String,
        unavailableMessage: String,
        searchingMessage: String,
        locationErrorMessage: String,
        geocodingMessage: String,
        geocodingErrorMessage: String,
        addressNotFoundMessage: String,
    };

    connect() {
        if (!this.allowedValue) {
            this.disableLocationButton();
            // Une recherche manuelle par adresse reste valide même lorsque
            // l'accès au GPS est désactivé dans le profil.
            if (!this.originValue) {
                this.showStatus(this.disabledMessageValue, true);
            }
        }
    }

    locate() {
        // La préférence du profil doit autoriser l'appel au GPS du navigateur.
        if (!this.allowedValue) {
            this.showStatus(this.enableMessageValue, true);
            return;
        }

        if (!("geolocation" in navigator)) {
            this.showStatus(this.unavailableMessageValue, true);
            return;
        }

        this.disableLocationButton();
        this.showStatus(this.searchingMessageValue);

        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                this.addressTarget.value = "";
                this.submitCoordinates(coords.latitude, coords.longitude, "device");
            },
            () => {
                this.enableLocationButton();
                this.showStatus(this.locationErrorMessageValue, true);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 300000,
            },
        );
    }

    async searchAddress(event) {
        event.preventDefault();

        const address = this.addressTarget.value.trim();
        if (address === "") {
            this.addressTarget.reportValidity();
            return;
        }

        this.setAddressSearchPending(true);
        this.showStatus(this.geocodingMessageValue);

        try {
            // Nominatim est appelé uniquement lors de la validation explicite
            // du formulaire et une seule réponse est demandée.
            const url = new URL(this.geocodingUrlValue);
            url.searchParams.set("q", address);
            url.searchParams.set("format", "jsonv2");
            url.searchParams.set("limit", "1");
            url.searchParams.set("countrycodes", "fr");
            url.searchParams.set("accept-language", document.documentElement.lang);

            const response = await fetch(url, {
                headers: { Accept: "application/json" },
                credentials: "omit",
                referrerPolicy: "strict-origin-when-cross-origin",
            });
            if (!response.ok) {
                throw new Error(`Nominatim ${response.status}`);
            }

            const results = await response.json();
            if (!Array.isArray(results) || results.length === 0) {
                this.setAddressSearchPending(false);
                this.showStatus(this.addressNotFoundMessageValue, true);
                return;
            }

            const latitude = Number.parseFloat(results[0].lat);
            const longitude = Number.parseFloat(results[0].lon);
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                throw new Error("Coordonnées Nominatim invalides");
            }

            this.submitCoordinates(latitude, longitude, "address");
        } catch {
            this.setAddressSearchPending(false);
            this.showStatus(this.geocodingErrorMessageValue, true);
        }
    }

    submitCoordinates(latitude, longitude, source) {
        // Le formulaire GET conserve le filtre courant et transmet uniquement
        // l'origine nécessaire au calcul local des distances.
        this.latitudeTarget.value = latitude.toFixed(7);
        this.longitudeTarget.value = longitude.toFixed(7);
        this.sourceTarget.value = source;
        this.formTarget.submit();
    }

    setAddressSearchPending(pending) {
        this.submitTarget.disabled = pending;
        this.addressTarget.readOnly = pending;
    }

    disableLocationButton() {
        this.buttonTarget.disabled = true;
        this.buttonTarget.setAttribute("aria-disabled", "true");
    }

    enableLocationButton() {
        this.buttonTarget.disabled = false;
        this.buttonTarget.removeAttribute("aria-disabled");
    }

    showStatus(message, isError = false) {
        this.statusTarget.textContent = message;
        this.statusTarget.classList.toggle("text-red-600", isError);
        this.statusTarget.classList.toggle("text-slate-500", !isError);
    }
}


