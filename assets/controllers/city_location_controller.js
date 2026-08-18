// Détecte la position de l’utilisateur afin de proposer la ville SafeCity correspondante.
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["button", "city", "status"];

    static values = {
        allowed: Boolean,
        disabledMessage: String,
        enableMessage: String,
        unavailableMessage: String,
        searchingMessage: String,
        errorMessage: String,
        noCityMessage: String,
        detectedMessage: String,
    };

    connect() {
        if (!this.allowedValue) {
            this.buttonTarget.disabled = true;
            this.buttonTarget.setAttribute("aria-disabled", "true");
            this.showStatus(this.disabledMessageValue, true);
        }
    }

    locate() {
        // La préférence du profil interdit toute demande au navigateur, même
        // lorsque l’action Stimulus est appelée autrement que par le bouton.
        if (!this.allowedValue) {
            this.showStatus(this.enableMessageValue, true);
            return;
        }

        if (!("geolocation" in navigator)) {
            this.showStatus(this.unavailableMessageValue, true);
            return;
        }

        this.showStatus(this.searchingMessageValue);

        navigator.geolocation.getCurrentPosition(
            (position) => this.selectNearestCity(position.coords),
            () => this.showStatus(this.errorMessageValue, true),
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 300000,
            },
        );
    }

    selectNearestCity(coords) {
        const candidates = this.cityTargets
            .map((city) => ({
                element: city,
                latitude: Number.parseFloat(city.dataset.latitude),
                longitude: Number.parseFloat(city.dataset.longitude),
            }))
            .filter((city) => Number.isFinite(city.latitude) && Number.isFinite(city.longitude));

        if (candidates.length === 0) {
            this.showStatus(this.noCityMessageValue, true);
            return;
        }

        const nearestCity = candidates.reduce((nearest, city) => {
            const distance = this.distanceInKilometers(
                coords.latitude,
                coords.longitude,
                city.latitude,
                city.longitude,
            );

            return distance < nearest.distance ? { ...city, distance } : nearest;
        }, { distance: Number.POSITIVE_INFINITY });

        this.showStatus(this.detectedMessageValue.replace("%city%", nearestCity.element.dataset.cityName));
        nearestCity.element.requestSubmit();
    }

    distanceInKilometers(latitudeA, longitudeA, latitudeB, longitudeB) {
        const earthRadius = 6371;
        const latitudeDelta = this.toRadians(latitudeB - latitudeA);
        const longitudeDelta = this.toRadians(longitudeB - longitudeA);
        const latitudeARadians = this.toRadians(latitudeA);
        const latitudeBRadians = this.toRadians(latitudeB);

        const haversine =
            Math.sin(latitudeDelta / 2) ** 2
            + Math.cos(latitudeARadians)
            * Math.cos(latitudeBRadians)
            * Math.sin(longitudeDelta / 2) ** 2;

        return earthRadius * 2 * Math.atan2(Math.sqrt(haversine), Math.sqrt(1 - haversine));
    }

    toRadians(degrees) {
        return degrees * (Math.PI / 180);
    }

    showStatus(message, isError = false) {
        this.statusTarget.textContent = message;
        this.statusTarget.classList.toggle("text-red-600", isError);
        this.statusTarget.classList.toggle("text-slate-500", !isError);
    }
}


