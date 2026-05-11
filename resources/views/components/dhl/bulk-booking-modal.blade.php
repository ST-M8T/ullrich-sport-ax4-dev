<div class="modal fade" id="dhl-bulk-booking-modal" tabindex="-1" aria-labelledby="bulkBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkBookingModalLabel">DHL-Buchung (Massen)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form id="dhl-bulk-booking-form" method="post">
                @csrf
                <input type="hidden" name="action" value="bulk-book">
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle"></i>
                        <span id="selected-orders-count">0</span> Aufträge ausgewählt
                    </div>
                    <input type="hidden" name="order_ids" id="selected-order-ids">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="product_id" class="form-label">DHL-Produkt <span class="text-danger">*</span></label>
                            <select class="form-select" id="product_id" name="product_id" required>
                                <option value="">-- Bitte wählen --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="pickup_date" class="form-label">Abhol-Datum</label>
                            <input type="date" class="form-control" id="pickup_date" name="pickup_date" min="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Zusatzservices</label>
                        <div id="additional-services-container" class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                            <span class="text-muted">Produkt wählen, um Services zu laden …</span>
                        </div>
                        <input type="hidden" name="additional_services" id="selected-services">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary" id="bulk-booking-submit">
                        <span class="spinner-border spinner-border-sm d-none" id="bulk-booking-spinner"></span>
                        <span id="bulk-booking-submit-text">DHL buchen</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>