// Affiche sur Leaflet les professionnels transmis par le contrôleur Symfony.
import { Controller } from "@hotwired/stimulus";
import L from "leaflet";

export default class extends Controller {
    static targets = ["canvas"];

    static values = {
        services: Array,
        isPharmacy: Boolean,
        userLatitude: Number,
        userLongitude: Number,
        onDutyLabel: String,
        distanceMessage: String,
        userLabel: String,
    };

    connect() {
        this.map = L.map(this.canvasTarget, {
            zoomControl: false,
        }).setView([43.6045, 1.4442], 13);

        L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: "© OpenStreetMap contributors",
            maxZoom: 19,
        }).addTo(this.map);

        L.control.zoom({ position: "bottomright" }).addTo(this.map);
        this.renderMarkers();
    }

    disconnect() {
        this.map?.remove();
    }

    renderMarkers() {
        const bounds = [];

        this.servicesValue.forEach((service) => {
            const latitude = Number.parseFloat(service.latitude);
            const longitude = Number.parseFloat(service.longitude);
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                return;
            }

            // L'icône reprend la couleur de la rubrique sans dépendre des
            // images de marqueur externes habituellement utilisées par Leaflet.
            const colorClass = this.isPharmacyValue ? "bg-emerald-700" : "bg-violet-700";
            const icon = L.divIcon({
                className: "",
                html: `<span class="flex size-10 items-center justify-center rounded-2xl border-2 border-white ${colorClass} text-lg font-bold text-white shadow-lg">+</span>`,
                iconSize: [40, 40],
                iconAnchor: [20, 20],
            });

            L.marker([latitude, longitude], {
                icon,
                title: service.name,
            })
                .bindPopup(this.buildPopup(service))
                .addTo(this.map);

            bounds.push([latitude, longitude]);
        });

        if (this.hasUserLatitudeValue && this.hasUserLongitudeValue) {
            const userPosition = [this.userLatitudeValue, this.userLongitudeValue];
            L.circleMarker(userPosition, {
                radius: 8,
                color: "#ffffff",
                weight: 3,
                fillColor: "#0f172a",
                fillOpacity: 1,
            })
                .bindTooltip(this.userLabelValue)
                .addTo(this.map);
            bounds.push(userPosition);
        }

        if (bounds.length > 1) {
            this.map.fitBounds(bounds, { padding: [35, 35], maxZoom: 15 });
        } else if (bounds.length === 1) {
            this.map.setView(bounds[0], 15);
        }
    }

    buildPopup(service) {
        // textContent empêche qu'un nom ou une adresse enregistré en base soit
        // interprété comme du code HTML dans la fenêtre de la carte.
        const container = document.createElement("div");
        const name = document.createElement("strong");
        name.textContent = service.name;
        container.append(name);

        const address = document.createElement("p");
        address.textContent = service.address;
        address.className = "mt-1";
        container.append(address);

        if (service.onDuty) {
            const availability = document.createElement("span");
            availability.textContent = this.onDutyLabelValue;
            availability.className = "mt-1 block font-semibold text-emerald-700";
            container.append(availability);
        }

        if (Number.isFinite(service.distance)) {
            const distance = document.createElement("span");
            const formattedDistance = new Intl.NumberFormat(document.documentElement.lang, {
                maximumFractionDigits: 1,
                minimumFractionDigits: 1,
            }).format(service.distance);
            distance.textContent = this.distanceMessageValue.replace("%distance%", formattedDistance);
            distance.className = "mt-1 block font-semibold";
            container.append(distance);
        }

        return container;
    }
}


