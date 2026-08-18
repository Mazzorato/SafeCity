// Initialise la carte Leaflet et y place les signalements géolocalisés.
import { Controller } from "@hotwired/stimulus";
import L from "leaflet";

export default class extends Controller {
    static targets = ["canvas", "status"];

    static values = {
        reportsUrl: String,
        mercureUrl: String,
        detailBaseUrl: String,
        centerLatitude: Number,
        centerLongitude: Number,
        loadingMessage: String,
        loadErrorMessage: String,
        countMessage: String,
        incidentMessage: String,
        locationUnavailableMessage: String,
        locationSearchingMessage: String,
        locationCenteredMessage: String,
        locationErrorMessage: String,
        realtimeErrorMessage: String,
    };

    connect() {
        const latitude = this.hasCenterLatitudeValue ? this.centerLatitudeValue : 43.6045;
        const longitude = this.hasCenterLongitudeValue ? this.centerLongitudeValue : 1.444;

        this.map = L.map(this.canvasTarget, {
            zoomControl: false,
        }).setView([latitude, longitude], 13);

        L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: "© OpenStreetMap contributors",
            maxZoom: 19,
        }).addTo(this.map);

        L.control.zoom({ position: "bottomright" }).addTo(this.map);
        this.markerLayer = L.layerGroup().addTo(this.map);

        this.loadReports();
        this.subscribeToMercure();
    }

    disconnect() {
        this.eventSource?.close();
        this.map?.remove();
    }

    async loadReports() {
        this.showStatus(this.loadingMessageValue);

        try {
            const response = await fetch(this.reportsUrlValue, {
                headers: { Accept: "application/json" },
                credentials: "same-origin",
            });

            if (!response.ok) {
                throw new Error(`Réponse API ${response.status}`);
            }

            const payload = await response.json();
            this.renderReports(payload.data ?? []);
        } catch {
            this.showStatus(this.loadErrorMessageValue, true);
        }
    }

    renderReports(reports) {
        this.markerLayer.clearLayers();
        const bounds = [];

        reports.forEach((report) => {
            const latitude = Number.parseFloat(report.location?.latitude);
            const longitude = Number.parseFloat(report.location?.longitude);

            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                return;
            }

            const statusClass = {
                reported: "bg-red-600",
                in_progress: "bg-blue-600",
                resolved: "bg-emerald-600",
            }[report.status] ?? "bg-slate-700";

            const icon = L.divIcon({
                className: "",
                html: `<span class="flex size-11 items-center justify-center rounded-2xl border-2 border-white ${statusClass} text-lg font-bold text-white shadow-lg">!</span>`,
                iconSize: [44, 44],
                iconAnchor: [22, 22],
            });

            const marker = L.marker([latitude, longitude], {
                icon,
                title: report.category?.name ?? this.incidentMessageValue,
            });

            marker.on("click", () => {
                window.location.assign(`${this.detailBaseUrlValue}/${report.id}`);
            });
            marker.addTo(this.markerLayer);
            bounds.push([latitude, longitude]);
        });

        if (bounds.length > 1) {
            this.map.fitBounds(bounds, { padding: [35, 35], maxZoom: 15 });
        } else if (bounds.length === 1) {
            this.map.setView(bounds[0], 15);
        }

        this.showStatus(this.countMessageValue.replace("%count%", String(bounds.length)));
    }

    recenter() {
        if (!("geolocation" in navigator)) {
            this.showStatus(this.locationUnavailableMessageValue, true);
            return;
        }

        this.showStatus(this.locationSearchingMessageValue);
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                this.map.setView([coords.latitude, coords.longitude], 15);
                this.showStatus(this.locationCenteredMessageValue);
            },
            () => this.showStatus(this.locationErrorMessageValue, true),
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 },
        );
    }

    subscribeToMercure() {
        if (!this.hasMercureUrlValue || this.mercureUrlValue === "") {
            return;
        }

        this.eventSource = new EventSource(this.mercureUrlValue);
        this.eventSource.onmessage = () => this.loadReports();
        this.eventSource.onerror = () => {
            this.showStatus(this.realtimeErrorMessageValue, true);
        };
    }

    showStatus(message, isError = false) {
        if (!this.hasStatusTarget) {
            return;
        }

        this.statusTarget.textContent = message;
        this.statusTarget.classList.toggle("text-red-600", isError);
        this.statusTarget.classList.toggle("text-slate-500", !isError);
    }
}
