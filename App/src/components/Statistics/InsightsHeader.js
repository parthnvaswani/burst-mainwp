import { __ } from '@wordpress/i18n';
import { useInsightsStore } from '../../store/useInsightsStore';
import PopoverFilter from '../Common/PopoverFilter';

/**
 * InsightsHeader renders the metric selector popover for the Insights block.
 *
 * @param {Object}   props                - Component props.
 * @param {string[]} props.selectedMetrics - Currently active metric keys.
 * @param {Object}   props.filters         - Active block filters (used to show/hide conversions).
 * @return {JSX.Element} The rendered popover header.
 */
const InsightsHeader = ({ selectedMetrics, filters }) => {
	const setMetrics = useInsightsStore( ( state ) => state.setMetrics );

	const insightsOptions = {
		pageviews: {
			label: __( 'Pageviews', 'burst-mainwp' ),
			default: true
		},
		visitors: {
			label: __( 'Visitors', 'burst-mainwp' ),
			default: true
		},
		sessions: {
			label: __( 'Sessions', 'burst-mainwp' )
		},
		bounces: {
			label: __( 'Bounces', 'burst-mainwp' )
		},
		conversions: {
			label: __( 'Conversions', 'burst-mainwp' ),
			default: 0 < filters.goal_id
		}
	};

	const onApply = ( value ) => {
		setMetrics( value );
	};

	return (
		<PopoverFilter
			selectedOptions={selectedMetrics}
			options={insightsOptions}
			onApply={onApply}
		/>
	);
};

export default InsightsHeader;
