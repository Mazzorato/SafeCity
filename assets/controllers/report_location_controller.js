// Géolocalise le signalement et tente d’obtenir une adresse lisible par géocodage inverse.
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["latitude", "longitude", "address", "display", "status", "button"];

    static values = {
        allowed: Boolean,
        automatic: Boolean,
        locale: String,
        confirmedMessage: String,
        disabledMessage: String,
        enableMessage: String,
        unavailableMessage: String,
        searchingMessage: String,
        errorMessage: String,
        addressSearchingMessage: String,
        addressUnavailableMessage: String,
    };

    connect() {
        if (this.latitudeTarget.value && this.longitudeTarget.value) {
            this.displayTarget.textContent = this.addressTarget.value || this.coordinateLabel(
                this.latitudeTarget.value,
                this.longitudeTarget.value,
            );
            this.showStatus(this.confirmedMessageValue);
            return;
        }

        if (!this.allowedValue) {
            this.setAllowed(false);
            this.showStatus(this.disabledMessageValue, true);
            return;
        }

        if (this.automaticValue) {
            this.locate();
        }
    }

    locate(event) {
        event?.preventDefault();

        // Le contrôle est répété ici afin qu’un appel direct à l’action
        // Stimulus ne puisse pas contourner la préférence du profil.
        if (!this.allowedValue) {
            this.showStatus(this.enableMessageValue, true);
            return;
        }

        if (this.locating) {
            return;
        }

        if (!("geolocation" in navigator)) {
            this.showStatus(this.unavailableMessageValue, true);
            return;
        }

        this.setLocating(true);
        this.showStatus(this.searchingMessageValue);
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => this.saveLocation(coords),
            () => {
                this.setLocating(false);
                this.showStatus(this.errorMessageValue, true);
            },
            {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 120000,
            },
        );
    }

    async saveLocation(coords) {
        const latitude = coords.latitude.toFixed(7);
        const longitude = coords.longitude.toFixed(7);

        this.latitudeTarget.value = latitude;
        this.longitudeTarget.value = longitude;
        this.displayTarget.textContent = this.coordinateLabel(latitude, longitude);
        this.showStatus(this.addressSearchingMessageValue);

        try {
            const url = new URL("https://nominatim.openstreetmap.org/reverse");
            url.searchParams.set("format", "jsonv2");
            url.searchParams.set("lat", latitude);
            url.searchParams.set("lon", longitude);
            url.searchParams.set("zoom", "18");
            url.searchParams.set("addressdetails", "0");
            url.searchParams.set("accept-language", this.localeValue);

            const response = await fetch(url, {
                headers: { Accept: "application/json" },
            });

            if (!response.ok) {
                throw new Error(`Réponse de géocodage ${response.status}`);
            }

            const result = await response.json();
            const address = (result.display_name || this.coordinateLabel(latitude, longitude)).slice(0, 255);
            this.addressTarget.value = address;
            this.displayTarget.textContent = address;
            this.showStatus(this.confirmedMessageValue);
        } catch {
            this.addressTarget.value = this.coordinateLabel(latitude, longitude);
            this.showStatus(this.addressUnavailableMessageValue);
        } finally {
            this.setLocating(false);
        }
    }

    coordinateLabel(latitude, longitude) {
        return `${latitude}, ${longitude}`;
    }

    showStatus(message, isError = false) {
        this.statusTarget.textContent = message;
        this.statusTarget.classList.toggle("text-red-600", isError);
        this.statusTarget.classList.toggle("text-slate-500", !isError);
    }

    setLocating(locating) {
        this.locating = locating;
        this.buttonTarget.disabled = locating;
        this.buttonTarget.classList.toggle("cursor-wait", locating);
        this.buttonTarget.classList.toggle("opacity-50", locating);
    }

    setAllowed(allowed) {
        this.buttonTarget.disabled = !allowed;
        this.buttonTarget.setAttribute("aria-disabled", allowed ? "false" : "true");
        this.buttonTarget.classList.toggle("cursor-not-allowed", !allowed);
        this.buttonTarget.classList.toggle("opacity-50", !allowed);
    }
}
