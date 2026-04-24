import { useMemo, useCallback } from '@wordpress/element';
import { ResponsiveLine } from '@nivo/line';
import { InsightsTooltip } from './InsightsTooltip';
import { formatAxisLabel, getChartXAxisTickValues } from '@/utils/formatting';

/**
 * Transforms API response data into the format expected by Nivo ResponsiveLine.
 * Each x value is a JS Date object derived from the corresponding Unix timestamp.
 *
 * @param {Object}   data                    - API response object.
 * @param {Array}    data.datasets            - Dataset definitions with label, data, and borderColor.
 * @param {number[]} timestamps              - Array of Unix timestamps (UTC seconds) per data point.
 * @return {Array} Nivo-compatible line series array.
 */
function transformToNivoFormat( data, timestamps ) {
	if ( ! data?.datasets || ! timestamps?.length ) {
		return [];
	}

	return data.datasets.map( ( dataset ) => ({
		id: dataset.label,
		color: dataset.borderColor,
		data: timestamps.map( ( ts, i ) => ({
			x: new Date( ts * 1000 ),
			y: dataset.data[ i ] ?? 0
		}) )
	}) );
}

/**
 * InsightsGraph renders the multi-line chart for the Insights block.
 * Accepts raw API data with Unix timestamps and formats the x-axis using
 * native Intl.DateTimeFormat via the insightsDateFormatting utility.
 *
 * @param {Object}   props                    - Component props.
 * @param {Object}   props.data               - API response with datasets.
 * @param {number[]} props.timestamps         - Unix timestamps (UTC seconds) per point.
 * @param {string}   props.interval           - Active grouping: 'hour'|'day'|'week'|'month'.
 * @param {boolean}  props.spansMultipleYears - Whether the range covers more than one year.
 * @return {JSX.Element} The rendered line chart.
 */
const InsightsGraph = ({ data, timestamps, interval, spansMultipleYears }) => {
	const nivoData = useMemo(
		() => transformToNivoFormat( data, timestamps ),
		[ data, timestamps ]
	);

	const allDates = useMemo(
		() => ( timestamps ?? []).map( ( ts ) => new Date( ts * 1000 ) ),
		[ timestamps ]
	);

	const xTickValues = useMemo(
		() => getChartXAxisTickValues( allDates ),
		[ allDates ]
	);

	// Memoised tick formatter — called by Nivo for every visible tick label.
	const formatTick = useCallback(
		( value ) => {

			// Nivo passes the raw Date object for time scales.
			const ts = value instanceof Date ? value.getTime() / 1000 : Number( value ) / 1000;
			return formatAxisLabel( ts, interval ?? 'day', spansMultipleYears ?? false );
		},
		[ interval, spansMultipleYears ]
	);

	// Slice tooltip wrapper so we can pass the interval down without prop-drilling through Nivo.
	const sliceTooltip = useCallback(
		({ slice }) => <InsightsTooltip slice={slice} interval={interval ?? 'day'} />,
		[ interval ]
	);

	return (
		<ResponsiveLine
			data={ nivoData }
			margin={{ top: 30, right: 48, bottom: 56, left: 72 }}
			xScale={{ type: 'time', format: 'native' }}
			xFormat="time:%Q"
			yScale={{ type: 'linear', min: 0, max: 'auto', stacked: false }}
			colors={{ datum: 'color' }}
			axisBottom={{
				tickSize: 0,
				tickPadding: 12,
				tickValues: xTickValues,
				format: formatTick
			}}
			axisLeft={{
				tickSize: 0,
				tickPadding: 12,
				tickValues: 6
			}}
			enableGridX={ false }
			enableGridY={ true }
			gridYValues={ 6 }
			pointSize={ 8 }
			lineWidth={ 3 }
			enablePointLabel={ false }
			enableSlices="x"
			sliceTooltip={ sliceTooltip }
			theme={{
				grid: { line: { stroke: '#dee2e6', strokeWidth: 1 } },
				axis: {
					ticks: { text: { fill: '#6c757d', fontSize: 12 } },
					domain: { line: { stroke: '#adb5bd', strokeWidth: 1 } }
				}
			}}
			curve="catmullRom"
		/>
	);
};

export default InsightsGraph;
