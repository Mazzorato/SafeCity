// Actualise le compteur de notifications à partir des événements Mercure.
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["permission", "toast", "title", "message"];
    static values = {
        url: String,
        enabled: Boolean,
    };

    connect() {
        this.hideTimer = null;
        this.eventSource = null;

        if (!this.enabledValue || !this.hasUrlValue) {
            return;
        }

        this.updatePermissionButton();
        this.eventSource = new EventSource(this.urlValue);
        this.eventSource.onmessage = (event) => this.receive(event);
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
        }
        if (this.hideTimer) {
            window.clearTimeout(this.hideTimer);
        }
    }

    async requestPermission() {
        if (!("Notification" in window)) {
            this.permissionTarget.classList.add("hidden");
            return;
        }

        await Notification.requestPermission();
        this.updatePermissionButton();
    }

    receive(event) {
        let notification;
        try {
            notification = JSON.parse(event.data);
        } catch {
            return;
        }

        this.titleTarget.textContent = notification.title || "SafeCity";
        this.messageTarget.textContent = notification.message || "";
        this.toastTarget.classList.remove("hidden");

        if (this.hideTimer) {
            window.clearTimeout(this.hideTimer);
        }
        this.hideTimer = window.setTimeout(() => this.close(), 7000);

        if ("Notification" in window && Notification.permission === "granted" && document.visibilityState !== "visible") {
            new Notification(notification.title || "SafeCity", {
                body: notification.message || "",
                tag: `safecity-${notification.id || "notification"}`,
            });
        }
    }

    close() {
        this.toastTarget.classList.add("hidden");
    }

    updatePermissionButton() {
        if (!this.hasPermissionTarget) {
            return;
        }

        const shouldShow = "Notification" in window && Notification.permission === "default";
        this.permissionTarget.classList.toggle("hidden", !shouldShow);
    }
}
