import "./bootstrap";
import Alpine from "alpinejs";
import intersect from "@alpinejs/intersect";

Alpine.plugin(intersect);

// Inquiry modal component
window.inquiryModal = function (config) {
    return {
        open: false,
        loading: false,
        sent: false,
        error: "",
        to: config.to || "/inquiry",
        product: config.product || null,
        form: {
            first_name: "",
            last_name: "",
            email: "",
            phone: "",
            message: "",
        },
        init() {
            this._openHandler = (e) => {
                if (e.detail?.product) {
                    this.product = e.detail.product;
                }
                this.open = true;
            };
            window.addEventListener("open-inquiry-modal", this._openHandler);
        },
        destroy() {
            if (this._openHandler) {
                window.removeEventListener("open-inquiry-modal", this._openHandler);
            }
        },
        close() {
            this.open = false;
            this.error = "";
            if (this.sent) {
                this.form = { first_name: "", last_name: "", email: "", phone: "", message: "" };
                this.sent = false;
            }
        },
        async submit() {
            this.error = "";
            this.loading = true;

            try {
                const payload = {
                    ...this.form,
                    product_name: this.product?.name || null,
                    product_slug: this.product?.slug || null,
                    product_url: this.product?.url || null,
                };

                const response = await fetch(this.to, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN":
                            document.querySelector('meta[name="csrf-token"]')?.content || "",
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    this.sent = true;
                } else {
                    this.error = data.message || "Something went wrong. Please try again.";
                }
            } catch {
                this.error = "Network error. Please check your connection and try again.";
            } finally {
                this.loading = false;
            }
        },
    };
};

// Product carousel component for home page
window.productCarousel = function (totalItems) {
    return {
        currentIndex: 0,
        totalItems: totalItems,
        maxIndex: Math.max(0, totalItems - 3), // Show 3 at a time
        prev() {
            this.currentIndex = this.currentIndex > 0 ? this.currentIndex - 1 : this.maxIndex;
        },
        next() {
            this.currentIndex = this.currentIndex < this.maxIndex ? this.currentIndex + 1 : 0;
        },
    };
};

// Wishlist store — shared reactive state for heart buttons and navbar badge
Alpine.store("wishlist", {
    ids: [],
    count: 0,
    items: [],
    open: false,
    loading: false,

    async init() {
        try {
            const res = await fetch("/api/wishlist/ids");
            const data = await res.json();
            this.ids = data.ids || [];
            this.count = this.ids.length;
        } catch {
            // Silently fail — wishlist is non-critical
        }
    },

    has(productId) {
        return this.ids.includes(productId);
    },

    async toggle(productId) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
        try {
            const res = await fetch(`/api/wishlist/toggle/${productId}`, {
                method: "POST",
                headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" },
            });
            const data = await res.json();
            if (data.wishlisted) {
                this.ids.push(productId);
            } else {
                this.ids = this.ids.filter((id) => id !== productId);
            }
            this.count = this.ids.length;

            // Refresh items in the background so dropdown is current when opened
            await this.loadItems();
        } catch {
            // Silently fail
        }
    },

    async loadItems() {
        this.loading = true;
        try {
            const res = await fetch("/api/wishlist/items");
            const data = await res.json();
            this.items = data.items || [];
        } catch {
            this.items = [];
        } finally {
            this.loading = false;
        }
    },

    async togglePanel() {
        this.open = !this.open;
        if (this.open) {
            await this.loadItems();
        }
    },
});

window.Alpine = Alpine;
Alpine.start();
