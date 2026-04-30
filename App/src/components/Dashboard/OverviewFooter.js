import { useState, useMemo } from 'react';
import * as burst_api from '../../utils/api';
import { __ } from '@wordpress/i18n';
import Icon from '../../utils/Icon';
import Tooltip from '@/components/Common/Tooltip';
import { getRelativeTime } from '../../utils/formatting';
import ButtonInput from '@/components/Inputs/ButtonInput';

/**
 * OverviewFooter component to display tracking status
 *
 * @return { React.ReactElement } OverviewFooter component
 */
const OverviewFooter = () => {
	const [ trackingType, setTrackingType ] = useState( 'loading' ); // loading, error,
	// rest, endpoint,
	// disabled
	const [ lastChecked, setLastChecked ] = useState( 0 );
	useMemo( () => {
		burst_api.getAction( 'tracking' ).then( ( response ) => {
			if (
				'beacon' === response.status ||
				'rest' === response.status ||
				'disabled' === response.status
			) {
				const status = response.status ? response.status : 'error';
				const last_test = response.last_test ?
					response.last_test :
					__( 'Just now', 'burst-mainwp' );
				setTrackingType( status );
				setLastChecked( last_test );
			} else {
				setTrackingType( 'error' );
				setLastChecked( false );
			}
		});
	}, []);

	const trackingLastCheckedText =
		__( 'Last checked:', 'burst-mainwp' ) +
		' ' +
		getRelativeTime( new Date( lastChecked * 1000 ) ); // times 1000 because JS
	// uses milliseconds
	const trackingTexts = {
		loading: __( 'Loading tracking status…', 'burst-mainwp' ),
		error: __( 'Error checking tracking status', 'burst-mainwp' ),
		rest: __( 'Tracking with REST API', 'burst-mainwp' ),
		beacon: __( 'Tracking with an endpoint', 'burst-mainwp' ),
		disabled: __( 'Tracking is disabled', 'burst-mainwp' )
	};
	const trackingTooltipTexts = {
		loading: '',
		error: __(
			'Tracking does not seem to work. Check manually or contact support.',
			'burst-mainwp'
		),
		rest: __(
			'Tracking is working. You are using the REST API to collect statistics.',
			'burst-mainwp'
		),
		beacon: __(
			'Tracking is working. You are using the Burst endpoint to collect statistics. This type of tracking is accurate and lightweight.',
			'burst-mainwp'
		),
		disabled: __( 'Tracking is disabled', 'burst-mainwp' )
	};
	const trackingIcons = {
		loading: {
			icon: 'loading',
			color: 'black'
		},
		error: {
			icon: 'circle-times',
			color: 'red'
		},
		rest: {
			icon: 'circle-check',
			color: 'green'
		},
		beacon: {
			icon: 'circle-check',
			color: 'green'
		},
		disabled: {
			icon: 'circle-times',
			color: 'red'
		}
	};
	const trackingTooltipText =
		trackingTooltipTexts[trackingType] + ' ' + trackingLastCheckedText;
	const trackingText = trackingTexts[trackingType];
	const trackingIcon = trackingIcons[trackingType].icon;
	const trackingIconColor = trackingIcons[trackingType].color;

	return (
		<>
			<ButtonInput btnVariant={'tertiary'} link={{ to: '/statistics' }}>
				{__( 'View my statistics', 'burst-mainwp' )}
			</ButtonInput>

			<Tooltip content={trackingTooltipText}>
				<div className="w-max text-text-gray flex items-center min-w-0 leading-none gap-1.5 no-underline burst-tooltip-trackingtext">
					<Icon name={trackingIcon} color={trackingIconColor} />
					<div>{trackingText}</div>
				</div>
			</Tooltip>
		</>
	);
};

export default OverviewFooter;
