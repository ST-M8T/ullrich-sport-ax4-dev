/**
 * Main Application Entry Point
 * @module app
 *
 * Imports all modules (analog to app.css importing all CSS modules)
 */

// Core
import './core/bootstrap';
import './core/http';
import './core/string';
import './core/json';
import './core/ui';
import './core/a11y';

// Components
import './components/tabs';
import './components/modal/base';
import './components/bootstrap-modal';
import './components/theme';
import './components/settings-modal';

// Domains
import './domains/monitoring/modal';
import './domains/fulfillment/masterdata';
import './domains/fulfillment/dhl-product-catalog';
import './domains/fulfillment/dhl-freight-settings';
import './domains/fulfillment/dhl-booking-form';
import './domains/fulfillment/dhl-package-editor';
import './domains/fulfillment/dhl-price-quote';
import './domains/dispatch/scans-modal';
import './domains/tracking/overview';

// Utilities
import './utilities/inline-forms';
