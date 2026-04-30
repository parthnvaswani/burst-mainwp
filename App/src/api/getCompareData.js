import { getData } from '@/utils/api';
import {
	formatNumber,
	formatTime,
	getAbsoluteChangePercentage,
	getChangePercentage,
	getPercentage
} from '@/utils/formatting';
import { __ } from '@wordpress/i18n';

const metrics = {
	pageviews: __( 'Pageviews', 'burst-mainwp' ),
	sessions: __( 'Sessions', 'burst-mainwp' ),
	visitors: __( 'Visitors', 'burst-mainwp' ),
	bounce_rate: __( 'Bounce Rate', 'burst-mainwp' )
};

const goalMetrics = {
	conversions: __( 'Conversions', 'burst-mainwp' ),
	pageviews: __( 'Pageviews', 'burst-mainwp' ),
	sessions: __( 'Sessions', 'burst-mainwp' ),
	visitors: __( 'Visitors', 'burst-mainwp' )
};

const templates = {
	default: {
		pageviews: ( curr ) => ({
			title: __( 'Pageviews', 'burst-mainwp' ),
			subtitle: __(
				'%s pageviews per session',
				'burst-mainwp'
			).replace( '%s', formatNumber( curr.pageviews / curr.sessions ) ),
			value: formatNumber( curr.pageviews ),
			exactValue: curr.pageviews
		}),
		sessions: ( curr ) => ({
			title: __( 'Sessions', 'burst-mainwp' ),
			subtitle: __( '%s per session', 'burst-mainwp' ).replace(
				'%s',
				formatTime(
					( curr.pageviews / curr.sessions ) * curr.avg_time_on_page
				)
			),
			value: formatNumber( curr.sessions ),
			exactValue: curr.sessions
		}),
		visitors: ( curr ) => ({
			title: __( 'Visitors', 'burst-mainwp' ),
			subtitle: __( '%s are new visitors', 'burst-mainwp' ).replace(
				'%s',
				getPercentage( curr.first_time_visitors, curr.visitors )
			),
			value: formatNumber( curr.visitors ),
			exactValue: curr.visitors
		}),
		bounce_rate: ( curr ) => ({
			title: __( 'Bounce Rate', 'burst-mainwp' ),
			subtitle: __( '%s visitors bounced', 'burst-mainwp' ).replace(
				'%s',
				curr.bounced_sessions
			),
			value: formatNumber( curr.bounce_rate ) + '%',
			exactValue: curr.bounce_rate
		})
	},
	goalSelected: {
		conversions: ( curr ) => ({
			title: __( 'Conversions', 'burst-mainwp' ),
			subtitle: __(
				'%s of pageviews converted',
				'burst-mainwp'
			).replace( '%s', getPercentage( curr.conversion_rate, 100 ) ),
			value: formatNumber( curr.conversions ),
			exactValue: curr.conversions
		}),
		pageviews: ( curr ) => ({
			title: __( 'Pageviews', 'burst-mainwp' ),
			subtitle: __(
				'%s pageviews per conversion',
				'burst-mainwp'
			).replace( '%s', formatNumber( curr.pageviews / curr.conversions ) ),
			value: formatNumber( curr.pageviews ),
			exactValue: curr.pageviews
		}),
		sessions: ( curr ) => ({
			title: __( 'Sessions', 'burst-mainwp' ),
			subtitle: __(
				'%s sessions per conversion',
				'burst-mainwp'
			).replace( '%s', formatNumber( curr.sessions / curr.conversions ) ),
			value: formatNumber( curr.sessions ),
			exactValue: curr.sessions
		}),
		visitors: ( curr ) => ({
			title: __( 'Visitors', 'burst-mainwp' ),
			subtitle: __(
				'%s visitors per conversion',
				'burst-mainwp'
			).replace( '%s', formatNumber( curr.visitors / curr.conversions ) ),
			value: formatNumber( curr.visitors ),
			exactValue: curr.visitors
		})
	}
};

const transformCompareData = ( response ) => {
	const data = {};
	const curr = response.current;
	const prev = response.previous;

	let templateType = 'default';
	let selectedMetrics = metrics;

	if ( 'goals' === response.view ) {
		templateType = 'goalSelected';
		selectedMetrics = goalMetrics;
	}
	const selectedTemplate = templates[templateType];

	Object.entries( selectedMetrics ).forEach( ([ key ]) => {
		const templateFunction =
			selectedTemplate[key] || templates.default[key];

		let change = {};
		if ( 'bounce_rate' === key ) {
			change = getAbsoluteChangePercentage( curr[key], prev[key]);

			change.status =
				'positive' === change.status ? 'negative' : 'positive';
		} else {
			change = getChangePercentage( curr[key], prev[key]);
		}

		data[key] = {
			...templateFunction( curr, prev ),
			change: change.val,
			changeStatus: change.status
		};
	});
	return data;
};

/**
 * Get live visitors
 * @param {Object} args
 * @param {string} args.startDate
 * @param {string} args.endDate
 * @param {string} args.range
 * @param {Object} args.filters
 * @param          args.args
 * @return {Promise<*>}
 */
const getCompareData = async({ startDate, endDate, range, args }) => {
	const { data } = await getData( 'compare', startDate, endDate, range, args );
	return transformCompareData( data );
};

export default getCompareData;
