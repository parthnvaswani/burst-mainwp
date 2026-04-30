import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import RadioButtonsInput from '@/components/Inputs/RadioButtonsInput';
import { type FilterConfig } from '@/config/filterConfig';

interface BooleanFilterSetupProps {
	filterKey: string;
	config: FilterConfig;
	initialValue?: string;
	onChange: ( value: string ) => void;
}

interface RadioOption {
	type: string;
	icon: string;
	label: string;
	description?: string;
}

interface RadioOptions {
	[key: string]: RadioOption;
}

const BooleanFilterSetup: React.FC<BooleanFilterSetupProps> = ({
	filterKey,
	initialValue = '',
	onChange
}) => {
	const [ value, setValue ] = useState<string>( initialValue );

	useEffect( () => {
		setValue( initialValue );
	}, [ initialValue ]);

	const handleChange = ( newValue: string ) => {
		setValue( newValue );
		onChange( newValue );
	};

	// Get filter-specific options based on the filter key
	const getFilterOptions = (): RadioOptions => {
		if ( 'bounces' === filterKey ) {
			return {
				'': {
					type: '',
					icon: 'user',
					label: __( 'All visitors (default)', 'burst-mainwp' )
				},
				include: {
					type: 'include',
					icon: 'bounce',
					label: __( 'Bounced visitors', 'burst-mainwp' )
				},
				exclude: {
					type: 'exclude',
					icon: 'user-check',
					label: __( 'Active visitors', 'burst-mainwp' )
				}
			};
		} else if ( 'new_visitor' === filterKey ) {
			return {
				'': {
					type: '',
					icon: 'user',
					label: __( 'All visitors (default)', 'burst-mainwp' )
				},
				include: {
					type: 'include',
					icon: 'user-plus',
					label: __( 'New visitors', 'burst-mainwp' )
				},
				exclude: {
					type: 'exclude',
					icon: 'user-check',
					label: __( 'Returning visitors', 'burst-mainwp' )
				}
			};
		} else if ( 'entry_exit_pages' === filterKey ) {
			return {
				'': {
					type: '',
					icon: 'user',
					label: __( 'All pages', 'burst-mainwp' )
				},
				entry: {
					type: 'entry',
					icon: 'user-plus',
					label: __( 'Entry pages', 'burst-mainwp' )
				},
				exit: {
					type: 'exit',
					icon: 'user-check',
					label: __( 'Exit pages', 'burst-mainwp' )
				}
			};
		}

		// Fallback for any other boolean filters
		return {
			'': {
				type: '',
				icon: 'user',
				label: __( 'All visitors (default)', 'burst-mainwp' ),
				description: __(
					'Show all visitors without filtering',
					'burst-mainwp'
				)
			},
			include: {
				type: 'include',
				icon: 'check',
				label: __( 'Include', 'burst-mainwp' ),
				description: __(
					'Include visitors matching this criteria',
					'burst-mainwp'
				)
			},
			exclude: {
				type: 'exclude',
				icon: 'times',
				label: __( 'Exclude', 'burst-mainwp' ),
				description: __(
					'Exclude visitors matching this criteria',
					'burst-mainwp'
				)
			}
		};
	};

	const radioOptions = getFilterOptions();

	return (
		<div className="flex flex-col gap-6">
			{/* Radio Options */}
			<div className="flex flex-col gap-3">
				<label className="block text-sm font-medium text-text-gray">
					{__( 'Filter option', 'burst-mainwp' )}
				</label>
				<RadioButtonsInput
					inputId={`${filterKey}-boolean`}
					options={radioOptions}
					value={value}
					onChange={handleChange}
					columns={1}
				/>
			</div>
		</div>
	);
};

export default BooleanFilterSetup;
