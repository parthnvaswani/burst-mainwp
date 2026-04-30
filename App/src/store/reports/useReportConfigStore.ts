import { create } from 'zustand';
import { __, sprintf } from '@wordpress/i18n';
import {
	ReportFormat,
	WizardStep,
	DayOfWeekType,
	FrequencyType,
	ContentItems,
	WeekOfMonthType, ReportLogStatus, ReportLogSeverity, BlockComponentProps
} from './types';
import InsightsBlock from '@/components/Statistics/InsightsBlock';
import CompareBlock from '@/components/Statistics/CompareBlock';
import DevicesBlock from '@/components/Statistics/DevicesBlock';
import WorldMapBlock from '@/components/Sources/WorldMapBlock';
import DataTableBlock from '@/components/Statistics/DataTableBlock';
import Sales from '@/components/Sales/Sales';
import TopPerformers from '@/components/Sales/TopPerformers';
import FunnelChartSection from '@/components/Sales/FunnelChartSection';
import Logo from '@/components/Reporting/ReportWizard/Blocks/Logo';
import {ComponentType} from 'react';
const AVAILABLE_CONTENT: ContentItems = [
	{
		id: 'logo',
		label: __( 'Logo', 'burst-mainwp' ),
		icon: 'image',
		pro: false,
		component: Logo
	},
	{
		id: 'insights',
		label: __( 'Insights', 'burst-mainwp' ),
		icon: 'bulb',
		pro: true,
		component: InsightsBlock
	},
	{
		id: 'compare_story',
		label: __( 'Compare', 'burst-mainwp' ),
		icon: 'arrow-down-up',
		pro: true,
		component: CompareBlock
	},
	{
		id: 'compare',
		label: __( 'Compare', 'burst-mainwp' ),
		icon: 'arrow-down-up',
		pro: false
	},
	{
		id: 'devices',
		label: __( 'Devices', 'burst-mainwp' ),
		icon: 'mobile',
		pro: true,
		component: DevicesBlock
	},
	{
		id: 'world',
		label: __( 'World Map', 'burst-mainwp' ),
		icon: 'world',
		pro: true,
		component: WorldMapBlock
	},
	{
		id: 'pages',
		label: __( 'Pages', 'burst-mainwp' ),
		icon: 'page',
		pro: true,
		component: DataTableBlock as ComponentType<BlockComponentProps>,
		blockProps: { allowedConfigs: [ 'pages' ] }
	},
	{
		id: 'referrers',
		label: __( 'Referrers', 'burst-mainwp' ),
		icon: 'external-link',
		pro: true,
		component: DataTableBlock as ComponentType<BlockComponentProps>,
		blockProps: { allowedConfigs: [ 'referrers' ] }
	},
	{
		id: 'locations',
		label: __( 'Locations', 'burst-mainwp' ),
		icon: 'map-pinned',
		pro: true,
		component: DataTableBlock as ComponentType<BlockComponentProps>,
		blockProps: { allowedConfigs: [ 'locations' ] }
	},
	{
		id: 'campaigns',
		label: __( 'Campaigns', 'burst-mainwp' ),
		icon: 'campaign',
		pro: true,
		component: DataTableBlock as ComponentType<BlockComponentProps>,
		blockProps: { allowedConfigs: [ 'campaigns' ] }
	},
	{
		id: 'sales',
		label: __( 'Sales', 'burst-mainwp' ),
		icon: 'shopping-cart',
		pro: true,
		component: Sales,
		ecommerce: true
	},
	{
		id: 'top_performers',
		label: __( 'Top Performers', 'burst-mainwp' ),
		icon: 'trophy',
		pro: true,
		component: TopPerformers,
		ecommerce: true
	},
	{
		id: 'funnel',
		label: __( 'Funnel', 'burst-mainwp' ),
		icon: 'filter',
		pro: true,
		component: FunnelChartSection,
		ecommerce: true
	},
	{
		id: 'most_visited_pages',
		label: __( 'Most visited pages', 'burst-mainwp' ),
		icon: 'page',
		pro: false
	},
	{
		id: 'top_referrers',
		label: __( 'Top referrers', 'burst-mainwp' ),
		icon: 'external-link',
		pro: false
	},
	{
		id: 'top_campaigns',
		label: __( 'Top campaigns', 'burst-mainwp' ),
		icon: 'campaign',
		pro: true
	},
	{
		id: 'countries',
		label: __( 'Top countries', 'burst-mainwp' ),
		icon: 'world',
		pro: true
	}
];

const STATUS_SEVERITY_CLASSES = {
	success: 'bg-green-50 text-green',
	error: 'bg-red-50 text-red',
	warning: 'bg-gray-200 text-text-black',
	info: 'bg-blue-50 text-blue'
};

const REPORT_LOG_STATUS_CONFIG: Record<
	ReportLogStatus,
	{
		severity: ReportLogSeverity;
	}
> = {
	ready_to_share: {
		severity: 'info'
	},
	sending_successful: {
		severity: 'success'
	},
	sending_failed: {
		severity: 'error'
	},
	email_domain_error: {
		severity: 'error'
	},
	email_address_error: {
		severity: 'error'
	},
	partly_sent: {
		severity: 'error'
	},
	cron_miss: {
		severity: 'warning'
	},
	concept: {
		severity: 'warning'
	},
	scheduled: {
		severity: 'info'
	},
	processing: {
		severity: 'info'
	}
};

const STEPS: WizardStep[] = [
	{ number: 1, label: __( 'Create', 'burst-mainwp' ), fields: [ 'create' ] },
	{ number: 2, label: __( 'Edit', 'burst-mainwp' ), fields: [ 'editContent' ] },
	{ number: 3, label: __( 'Recipients', 'burst-mainwp' ), fields: [ 'recipients' ] },
	{ number: 4, label: __( 'Review', 'burst-mainwp' ), fields: [ 'reportName' ] }
];

const FORMATS: ReportFormat[] = [
	{ key: 'classic', label: __( 'Classic', 'burst-mainwp' ), disabled: false, pro: false },
	{ key: 'story', label: __( 'Story', 'burst-mainwp' ), disabled: true, pro: true }
];

const capitalize = ( value: string ) =>
	value.charAt( 0 ).toUpperCase() + value.slice( 1 );

export const useReportConfigStore = create( () => ({
	availableContent: AVAILABLE_CONTENT,
	steps: STEPS,
	stepCount: STEPS.length,
	formats: FORMATS,
	reportLogStatusConfig: REPORT_LOG_STATUS_CONFIG,
	statusSeverityClasses: STATUS_SEVERITY_CLASSES,

	frequencyOptions: [
		{ value: 'daily', label: __( 'Daily', 'burst-mainwp' ) },
		{ value: 'weekly', label: __( 'Weekly', 'burst-mainwp' ) },
		{ value: 'monthly', label: __( 'Monthly', 'burst-mainwp' ) }
	],

	dayOptions: [
		{ value: 'monday', label: __( 'Monday', 'burst-mainwp' ) },
		{ value: 'tuesday', label: __( 'Tuesday', 'burst-mainwp' ) },
		{ value: 'wednesday', label: __( 'Wednesday', 'burst-mainwp' ) },
		{ value: 'thursday', label: __( 'Thursday', 'burst-mainwp' ) },
		{ value: 'friday', label: __( 'Friday', 'burst-mainwp' ) },
		{ value: 'saturday', label: __( 'Saturday', 'burst-mainwp' ) },
		{ value: 'sunday', label: __( 'Sunday', 'burst-mainwp' ) }
	],

	getTimeOptions: () =>
		Array.from({ length: 24 }, ( _, i ) => {
			const h = String( i ).padStart( 2, '0' );
			return { value: `${ h }:00`, label: `${ h }:00` };
		}),

	getMonthlyWeekdayOptions: () => [
		{ value: 1, label: __( 'First', 'burst-mainwp' ) },
		{ value: 2, label: __( 'Second', 'burst-mainwp' ) },
		{ value: 3, label: __( 'Third', 'burst-mainwp' ) },
		{ value: -1, label: __( 'Last', 'burst-mainwp' ) }
	],

	getWeekOfMonthTypeLabel: (
		rule: WeekOfMonthType,
		dayOfWeek?: DayOfWeekType
	): string => {
		const state = useReportConfigStore.getState();

		const weekLabel = state
			.getMonthlyWeekdayOptions()
			.find( ( o ) => o.value === rule )
			?.label;

		// Rules like "on the 15th" don’t need a weekday.
		if ( ! weekLabel ) {
			return '';
		}

		// Weekday-based rules (e.g. "First Monday").
		if ( dayOfWeek ) {
			const dayLabel = state.dayOptions.find(
				( d ) => d.value === dayOfWeek
			)?.label;

			return dayLabel ? `${ weekLabel } ${ dayLabel }` : weekLabel;
		}

		return weekLabel;
	},

	getScheduleLabel: (
		scheduled: boolean,
		frequency: FrequencyType,
		dayOfWeek?: DayOfWeekType,
		weekOfMonth?: WeekOfMonthType,
		sendTime?: string
	): string => {
		if ( ! scheduled ) {
			return __( 'No schedule', 'burst-mainwp' );
		}

		let label: string;

		switch ( frequency ) {
			case 'weekly': {
				if ( ! dayOfWeek ) {
					label = __( 'Weekly', 'burst-mainwp' );
					break;
				}

				const day = `${ capitalize( dayOfWeek ) }s`;
				label = sprintf(
					__( 'Weekly on %s', 'burst-mainwp' ),
					day
				);
				break;
			}

			case 'monthly': {
				if ( ! weekOfMonth ) {
					label = __( 'Monthly', 'burst-mainwp' );
					break;
				}

				const ruleLabel =
					useReportConfigStore
						.getState()
						.getWeekOfMonthTypeLabel( weekOfMonth, dayOfWeek );

				label = sprintf(
					__( 'Monthly on %s', 'burst-mainwp' ),
					ruleLabel
				);
				break;
			}

			default:
				label = __( 'Daily', 'burst-mainwp' );
		}

		return sendTime ?
			sprintf(
				__( '%s at %s', 'burst-mainwp' ),
				label,
				sendTime
			) :
			label;
	}
}) );
