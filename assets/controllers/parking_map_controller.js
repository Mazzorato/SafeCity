// Affiche les parkings et l'origine de recherche sur une carte Leaflet.
import { Controller } from "@hotwired/stimulus";
import L from "leaflet";

export default class extends Controller {
    static targets = ["canvas"];

    static values = {
        parkings: Array,
        originLatitude: Number,
        originLongitude: Number,
        originLabel: String,
        freeLabel: String,
        paidLabel: String,
        spacesMessage: String,
        distanceMessage: String,
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

        this.parkingsValue.forEach((parking) => {
            const latitude = Number.parseFloat(parking.latitude);
            const longitude = Number.parseFloat(parking.longitude);
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                return;
            }

            // La couleur du repère reprend l'indicateur gratuit ou payant de
            // la liste, sans image de marqueur externe.
            const colorClass = parking.free ? "bg-emerald-600" : "bg-blue-700";
            const icon = L.divIcon({
                className: "",
                html: `<span class="flex size-10 items-center justify-center rounded-2xl border-2 border-white ${colorClass} text-sm font-bold text-white shadow-lg">P</span>`,
                iconSize: [40, 40],
                iconAnchor: [20, 20],
            });

            L.marker([latitude, longitude], {
                icon,
                title: parking.name,
            })
                .bindPopup(this.buildPopup(parking))
                .addTo(this.map);

            bounds.push([latitude, longitude]);
        });

        if (this.hasOriginLatitudeValue && this.hasOriginLongitudeValue) {
            const origin = [this.originLatitudeValue, this.originLongitudeValue];
            L.circleMarker(origin, {
                radius: 8,
                color: "#ffffff",
                weight: 3,
                fillColor: "#0f172a",
                fillOpacity: 1,
            })
                .bindTooltip(this.originLabelValue)
                .addTo(this.map);
            bounds.push(origin);
        }

        if (bounds.length > 1) {
            this.map.fitBounds(bounds, { padding: [35, 35], maxZoom: 15 });
        } else if (bounds.length === 1) {
            this.map.setView(bounds[0], 15);
        }
    }

    buildPopup(parking) {
        // Tous les textes venant de la base utilisent textContent afin de ne
        // jamais être interprétés comme du HTML.
        const container = document.createElement("div");
        const name = document.createElement("strong");
        name.textContent = parking.name;
        container.append(name);

        const address = document.createElement("p");
        address.textContent = parking.address;
        address.className = "mt-1";
        container.append(address);

        const priceType = document.createElement("span");
        priceType.textContent = parking.free ? this.freeLabelValue : this.paidLabelValue;
        priceType.className = parking.free
            ? "mt-1 block font-semibold text-emerald-700"
            : "mt-1 block font-semibold text-slate-700";
        container.append(priceType);

        const spaces = document.createElement("span");
        spaces.textContent = this.spacesMessageValue.replace(
            "%count%",
            String(parking.availableSpots),
        );
        spaces.className = "mt-1 block";
        container.append(spaces);

        if (Number.isFinite(parking.distance)) {
            const distance = document.createElement("span");
            const formattedDistance = new Intl.NumberFormat(document.documentElement.lang, {
                maximumFractionDigits: 1,
                minimumFractionDigits: 1,
            }).format(parking.distance);
            distance.textContent = this.distanceMessageValue.replace("%distance%", formattedDistance);
            distance.className = "mt-1 block font-semibold";
            container.append(distance);
        }

        return container;
    }
}


