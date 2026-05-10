/**
 * Dispatch Scans Modal
 * @module domains/dispatch/scans-modal
 */

import { BaseModal } from '../../components/modal/base';
import { fetchJson } from '../../core/http';

class DispatchScansModal extends BaseModal {
    constructor(element) {
        super(element, {
            dialogSelector: '.modal-dialog, .app-modal__dialog',
            closeSelector: '[data-modal-close], .btn-close, [data-bs-dismiss]',
        });

        this.bodyElement = element.querySelector('#dispatch-scans-modal-body');
        this.titleElement = element.querySelector('#dispatch-scans-modal-label');
    }

    async open(trigger) {
        if (!trigger || !trigger.dataset.fetchUrl) {
            return;
        }

        const label = trigger.dataset.dispatchLabel ?? '';
        if (this.titleElement) {
            this.titleElement.textContent = `Scans für Liste ${label}`;
        }

        if (this.bodyElement) {
            this.bodyElement.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Lädt …</span>
                    </div>
                </div>`;
        }

        super.open();

        try {
            const payload = await fetchJson(trigger.dataset.fetchUrl, { method: 'GET' });
            const scans = Array.isArray(payload.scans) ? payload.scans : [];

            if (!this.bodyElement) {
                return;
            }

            if (scans.length === 0) {
                this.bodyElement.innerHTML = '<p class="mb-0 text-muted">Für diese Liste wurden noch keine Scans erfasst.</p>';
                return;
            }

            const formatter = new Intl.DateTimeFormat('de-DE', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });

            const rows = scans.map((scan) => {
                const capturedAt = scan.captured_at
                    ? formatter.format(new Date(scan.captured_at)).replace(',', '')
                    : '—';
                const username = scan.captured_by_username || (scan.captured_by_user_id ? `User #${scan.captured_by_user_id}` : '—');

                let orderId = '—';
                let tracking = '—';
                let country = '—';
                let orderType = '—';
                let express = '—';
                let itemsInfo = '—';
                let dimensions = '—';
                let weight = '—';
                let truckSlots = '—';

                if (scan.order) {
                    orderId = `#${scan.order.external_order_id || scan.shipment_order_id || '—'}`;

                    const trackingNumbers = scan.order.tracking_numbers || [];
                    tracking = trackingNumbers.length > 0 ? trackingNumbers.join(', ') : '—';

                    country = scan.order.destination_country || '—';
                    orderType = scan.order.order_type || 'Auftrag';

                    const isExpress = scan.metadata?.express || scan.metadata?.is_express || false;
                    express = isExpress ? 'Express' : '';

                    const items = scan.order.items || [];
                    if (items.length > 0) {
                        const totalQuantity = items.reduce((sum, item) => sum + (item.quantity || 0), 0);
                        const itemDescriptions = items
                            .map((item) => {
                                const qty = item.quantity || 0;
                                const desc = item.description || item.sku || '—';
                                return qty > 0 ? `${qty}x ${desc}` : desc;
                            })
                            .filter(Boolean)
                            .slice(0, 3);

                        itemsInfo = `${totalQuantity} Artikel`;
                        if (itemDescriptions.length > 0) {
                            itemsInfo += '<br><small>' + itemDescriptions.join('<br>') + '</small>';
                        }
                    }

                    const packages = scan.order.packages || [];
                    if (packages.length > 0) {
                        const dimsList = packages
                            .map((pkg, pkgIdx) => {
                                const dims = pkg.dimensions;
                                if (dims && dims.length_mm && dims.width_mm && dims.height_mm) {
                                    const lengthCm = Math.round(dims.length_mm / 10);
                                    const widthCm = Math.round(dims.width_mm / 10);
                                    const heightCm = Math.round(dims.height_mm / 10);
                                    const suffix = pkgIdx < packages.length - 1 ? ', ' : '';
                                    return `${pkg.quantity || 1}x ${lengthCm}×${widthCm}×${heightCm} cm${suffix}`;
                                }
                                return null;
                            })
                            .filter(Boolean);

                        if (dimsList.length > 0) {
                            dimensions = dimsList.join('');
                        }

                        const totalWeight = scan.order.total_weight_kg;
                        if (totalWeight !== null && totalWeight > 0) {
                            weight = Math.round(totalWeight);
                        }

                        const totalSlots = scan.order.total_truck_slots || 0;
                        if (totalSlots > 0) {
                            truckSlots = totalSlots.toString();
                        }
                    }
                } else if (scan.shipment_order_id) {
                    orderId = `#${scan.shipment_order_id}`;
                }

                return `
                    <tr>
                        <td>${orderId}</td>
                        <td>${tracking}</td>
                        <td>${country}</td>
                        <td>${orderType}</td>
                        <td>${express}</td>
                        <td>${itemsInfo}</td>
                        <td>${dimensions}</td>
                        <td>${weight}</td>
                        <td>${truckSlots}</td>
                        <td>${username}</td>
                        <td>${capturedAt}</td>
                        <td></td>
                    </tr>`;
            }).join('');

            this.bodyElement.innerHTML = `
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Auftrag</th>
                                <th>Tracking</th>
                                <th>Land</th>
                                <th>Typ</th>
                                <th>Express</th>
                                <th>Artikel</th>
                                <th>Maße</th>
                                <th>Gewicht</th>
                                <th>Plätze</th>
                                <th>Benutzer</th>
                                <th>Zeit</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>`;
        } catch (error) {
            if (this.bodyElement) {
                this.bodyElement.innerHTML = '<p class="text-danger mb-0">Scans konnten nicht geladen werden.</p>';
            }
        }
    }
}

function initialiseDispatchScansModal() {
    const modalElement = document.getElementById('dispatch-scans-modal');
    if (!modalElement) {
        return;
    }

    const modal = new DispatchScansModal(modalElement);

    document.querySelectorAll('[data-dispatch-scans-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            modal.open(trigger);
        });
    });
}

document.addEventListener('DOMContentLoaded', initialiseDispatchScansModal);


