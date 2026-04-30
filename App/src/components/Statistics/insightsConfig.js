import { __ } from '@wordpress/i18n';

/**
 * Maps metric keys to their translatable display labels.
 * Single source of truth used by InsightsBlock, InsightsGraph, and InsightsTooltip.
 */
export const METRIC_LABELS = {
	pageviews: __( 'Pageviews', 'burst-mainwp' ),
	visitors: __( 'Visitors', 'burst-mainwp' ),
	sessions: __( 'Sessions', 'burst-mainwp' ),
	bounces: __( 'Bounces', 'burst-mainwp' ),
	conversions: __( 'Conversions', 'burst-mainwp' )
};

/**
 * Maps metric keys to their design-system CSS custom property colors.
 * Used on the frontend to override the server-provided rgba values so all
 * metric line colors are fully token-aware and dark-mode compatible.
 */
export const METRIC_COLORS = {
	pageviews: 'var(--color-yellow-500)',
	visitors: 'var(--color-blue-400)',
	bounces: 'var(--color-red-500)',
	sessions: 'var(--color-orange-500)',
	conversions: 'var(--color-primary-700)'
};
