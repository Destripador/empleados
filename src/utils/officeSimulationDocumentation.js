/**
 * Single content source for the mandatory introduction and the permanent help.
 *
 * Keep presentation in the Vue components. New chapters only need an entry in
 * `chapters` plus their translated strings in l10n/en and l10n/es.
 *
 * @param {Function} t Nextcloud translation function
 */
export function buildOfficeSimulationDocumentation(t) {
	const introSteps = [
		{
			id: 'welcome',
			title: t('empleados', 'Office Simulation'),
			description: t('empleados', 'A visual experiment that brings the office to life.'),
			detail: t('empleados', 'It combines information from Employees and Nextcloud to create a scene with areas, roles, presence, activity and events.'),
			items: [
				t('empleados', 'Areas'),
				t('empleados', 'Roles'),
				t('empleados', 'Presence'),
				t('empleados', 'Events'),
			],
		},
		{
			id: 'data',
			title: t('empleados', 'An office built from data'),
			description: t('empleados', 'A few real signals help construct each employee and their place in the scene.'),
			detail: t('empleados', 'These signals shape the setting; they do not reproduce a person’s day minute by minute.'),
			items: [
				{ icon: '🙂', label: t('empleados', 'Name and avatar') },
				{ icon: '🏢', label: t('empleados', 'Area') },
				{ icon: '💼', label: t('empleados', 'Role') },
				{ icon: '🟢', label: t('empleados', 'Nextcloud presence') },
				{ icon: '📆', label: t('empleados', 'Join date and allowed events') },
				{ icon: '〽️', label: t('empleados', 'Normalized activity') },
			],
		},
		{
			id: 'emergence',
			title: t('empleados', 'Simple rules, unexpected patterns'),
			description: t('empleados', 'The idea is partly inspired by Conway’s Game of Life, but Office Simulation does not implement it literally.'),
			principle: t('empleados', 'Simple local rules can produce unexpected behavior across a whole system.'),
			detail: t('empleados', 'Nobody orders the avatars to form a group. Movement, proximity, office zones, affinity and chance can make one emerge.'),
			items: [
				t('empleados', 'Movement'),
				t('empleados', 'Proximity'),
				t('empleados', 'Office zones'),
				t('empleados', 'Affinity'),
				t('empleados', 'Chance'),
			],
		},
		{
			id: 'states',
			title: t('empleados', 'Each avatar can change state'),
			description: t('empleados', 'Another inspiration comes from state-transition models, including ideas associated with Turing machines. This is not a literal Turing machine.'),
			detail: t('empleados', 'Each virtual employee keeps a state and reacts to inputs. A Nextcloud presence change can modify what the avatar intends to do next.'),
			items: [
				{ icon: '💻', label: t('empleados', 'WORKING') },
				{ icon: '☕', label: t('empleados', 'COFFEE') },
				{ icon: '💬', label: t('empleados', 'CONVERSATION') },
				{ icon: '🛋️', label: t('empleados', 'RETURNING_HOME') },
			],
		},
		{
			id: 'events',
			title: t('empleados', 'Things that can happen'),
			description: t('empleados', 'Some signals begin with real data. Other scenes are invented by the simulation.'),
			realTitle: t('empleados', 'DATA SIGNALS'),
			realItems: [
				{ icon: '🎂', label: t('empleados', 'Birthday') },
				{ icon: '🎉', label: t('empleados', 'Work anniversary') },
				{ icon: '👋', label: t('empleados', 'New hire') },
				{ icon: '🟢', label: t('empleados', 'Nextcloud status') },
			],
			simulatedTitle: t('empleados', 'SIMULATED SCENES'),
			simulatedItems: [
				{ icon: '☕', label: t('empleados', 'Coffee break') },
				{ icon: '📅', label: t('empleados', 'Meeting') },
				{ icon: '💬', label: t('empleados', 'Conversation') },
				{ icon: '💢', label: t('empleados', 'Simulated conflict') },
				{ icon: '🖨️', label: t('empleados', 'Printer visit') },
				{ icon: '◌', label: t('empleados', 'Roaming and groups') },
			],
		},
		{
			id: 'privacy',
			title: t('empleados', 'Important: this is a simulation'),
			description: t('empleados', 'Real data helps build the setting, but the emerging scenes are fictional.'),
			notTitle: t('empleados', 'Office Simulation is not:'),
			notItems: [
				t('empleados', 'A productivity evaluation'),
				t('empleados', 'Exact employee tracking'),
				t('empleados', 'A record of real conversations or conflicts'),
				t('empleados', 'A measure of personal relationships'),
			],
			comparisons: [
				[t('empleados', 'More movement'), t('empleados', 'Better employee')],
				[t('empleados', 'Less movement'), t('empleados', 'Worse employee')],
				[t('empleados', 'Two avatars talking'), t('empleados', 'Those people are really talking')],
				[t('empleados', 'Conflict icon'), t('empleados', 'A real conflict exists')],
			],
			confirmation: t('empleados', 'I understand that the interactions and behaviors shown are part of a simulation and do not necessarily represent real actions or relationships.'),
		},
	]

	const chapters = [
		{
			id: 'introduction',
			number: '01',
			title: t('empleados', 'Introduction'),
			summary: t('empleados', 'Office Simulation is an experimental, explorable representation of an office. It turns a small set of organizational signals into a living visual scene.'),
			sections: [
				{
					title: t('empleados', 'What you are looking at'),
					paragraphs: [t('empleados', 'Every circle represents a virtual employee. Rooms, movement and encounters make the organization easier to explore, but they are a visual interpretation rather than a factual replay.')],
				},
				{
					title: t('empleados', 'How to read the scene'),
					bullets: [
						t('empleados', 'Areas and positions influence where an avatar starts and works.'),
						t('empleados', 'Presence and normalized activity influence visual state and energy.'),
						t('empleados', 'Local rules create fictional movement and social scenes.'),
					],
				},
			],
		},
		{
			id: 'inspiration',
			number: '02',
			title: t('empleados', 'Conceptual inspiration'),
			summary: t('empleados', 'The project borrows ideas from cellular automata, state-transition systems and agent-based simulation without implementing any of them literally.'),
			visual: 'emergence',
			sections: [
				{
					title: t('empleados', 'Conway and emergence'),
					paragraphs: [t('empleados', 'Conway’s Game of Life shows how a few local rules can generate large-scale patterns. Here, proximity, destinations, chance and social context play that conceptual role.')],
				},
				{
					title: t('empleados', 'State-transition models'),
					paragraphs: [t('empleados', 'Each employee node retains a current state and receives inputs. The result is a next intention, not a prediction about a real person.')],
				},
			],
		},
		{
			id: 'principles',
			number: '03',
			title: t('empleados', 'Simulation principles'),
			summary: t('empleados', 'The scene follows a few boundaries: real inputs are limited, simulated behavior is explicit, and no animation is evidence of individual performance.'),
			sections: [
				{
					title: t('empleados', 'Deterministic structure, variable behavior'),
					paragraphs: [t('empleados', 'The office layout and role profiles give the world structure. Timing, movement choices and encounters introduce variation while remaining inside configured limits.')],
				},
				{
					title: t('empleados', 'Interpretation boundary'),
					bullets: [
						t('empleados', 'A visual state is not a work evaluation.'),
						t('empleados', 'A simulated encounter is not a real-world interaction.'),
						t('empleados', 'Activity is normalized only to drive visual dynamics.'),
					],
				},
			],
		},
		{
			id: 'employees',
			number: '04',
			title: t('empleados', 'Employees and state machines'),
			summary: t('empleados', 'An employeeNode is the runtime representation of one employee. It combines identity, office placement, current intent and short-lived simulation state.'),
			visual: 'state',
			sections: [
				{
					title: t('empleados', 'Core fields'),
					terms: [
						{ name: 'employeeNode', description: t('empleados', 'The complete in-memory entity used by the physics and rendering loops.') },
						{ name: 'homeArea', description: t('empleados', 'The organizational area assigned from employee data.') },
						{ name: 'workAnchor', description: t('empleados', 'A stable point inside the work area that gives the avatar a place to return to.') },
						{ name: 'targetZone', description: t('empleados', 'The room or zone currently influencing the next destination.') },
						{ name: 'currentInteraction', description: t('empleados', 'A temporary simulated conversation, group or conflict context.') },
						{ name: 'energy', description: t('empleados', 'A normalized visual parameter that changes movement rhythm; it is not a score.') },
					],
				},
				{
					title: t('empleados', 'State changes'),
					paragraphs: [t('empleados', 'States such as working, coffee, conversation and returning home express what the simulated avatar is doing now. Inputs and timers select later transitions.')],
				},
			],
		},
		{
			id: 'spaces',
			number: '05',
			title: t('empleados', 'Areas, roles and spaces'),
			summary: t('empleados', 'The layout translates organizational areas into work rooms and adds shared functional spaces sized around the available population.'),
			sections: [
				{
					title: t('empleados', 'Organizational areas'),
					paragraphs: [t('empleados', 'Area membership determines homeArea. The layout builder packs work rooms dynamically and gives each employee a workAnchor inside the appropriate room.')],
				},
				{
					title: t('empleados', 'Positions'),
					paragraphs: [t('empleados', 'Position text is mapped to a role profile such as reception, management, support or a default profile. A profile changes preferences and mobility, not permissions or employment status.')],
				},
				{
					title: t('empleados', 'Functional rooms'),
					bullets: [
						t('empleados', 'Reception, coffee, meeting, lounge and printer zones support different simulated intentions.'),
						t('empleados', 'Role profiles can prefer certain rooms without preventing normal roaming.'),
						t('empleados', 'Furniture and boundaries are visual and physical constraints, not employee data.'),
					],
				},
				{
					title: t('empleados', 'Spots and anchors'),
					paragraphs: [t('empleados', 'A spot is a useful position inside a room. Anchors and spots keep destinations readable and reduce random clustering against walls or furniture.')],
				},
			],
		},
		{
			id: 'movement',
			number: '06',
			title: t('empleados', 'Movement and energy'),
			summary: t('empleados', 'Movement combines target selection, waypoints, local steering, collision avoidance and a configurable speed mode.'),
			sections: [
				{
					title: t('empleados', 'Choosing a destination'),
					paragraphs: [t('empleados', 'The current state and role profile propose a targetZone. Available spots and walkable routes turn that intention into waypoints.')],
				},
				{
					title: t('empleados', 'Energy is visual'),
					paragraphs: [t('empleados', 'Recent report activity is normalized into a bounded energy input. It changes animation rhythm and movement tendencies, never an employee rating or productivity conclusion.')],
				},
				{
					title: t('empleados', 'Physical constraints'),
					paragraphs: [t('empleados', 'Collision handling separates nearby nodes and keeps them inside walkable geometry. It exists to make the scene legible, not to model real office paths.')],
				},
			],
		},
		{
			id: 'nextcloud',
			number: '07',
			title: t('empleados', 'Nextcloud integration'),
			summary: t('empleados', 'The backend prepares a compact simulation payload from Employees records, reports and selected Nextcloud signals.'),
			visual: 'pipeline',
			sections: [
				{
					title: t('empleados', 'Inputs currently used'),
					bullets: [
						t('empleados', 'Employee identifier, display name and avatar reference.'),
						t('empleados', 'Area, position and join date from Employees.'),
						t('empleados', 'Report counts and minutes for a bounded recent period.'),
						t('empleados', 'Nextcloud presence, status icon or message, and last login when available.'),
					],
				},
				{
					title: t('empleados', 'What is not collected for this view'),
					paragraphs: [t('empleados', 'The simulation does not need message contents, file contents, call recordings, GPS data or a minute-by-minute browsing history.')],
				},
			],
		},
		{
			id: 'social',
			number: '08',
			title: t('empleados', 'Social interactions'),
			summary: t('empleados', 'Conversations and groups emerge from proximity checks, social zones, cooldowns, affinity and controlled probability.'),
			sections: [
				{
					title: t('empleados', 'Conversations'),
					paragraphs: [t('empleados', 'Nearby eligible nodes may enter a temporary conversation. The participants, timing and conversation itself are fictional.')],
				},
				{
					title: t('empleados', 'Affinity'),
					paragraphs: [t('empleados', 'Affinity is an internal simulation weight used to vary repeated encounters. It is not imported from a relationship database and must not be interpreted as a real bond.')],
				},
				{
					title: t('empleados', 'Groups and cooldowns'),
					paragraphs: [t('empleados', 'Small groups can form when local conditions align. Cooldowns stop the same nodes from continuously recreating an interaction.')],
				},
			],
		},
		{
			id: 'events',
			number: '09',
			title: t('empleados', 'Events'),
			summary: t('empleados', 'Events make the office react to a day or a temporary condition while keeping data-backed signals distinct from invented scenes.'),
			sections: [
				{
					title: t('empleados', 'Data-backed events'),
					paragraphs: [t('empleados', 'The current backend emits birthdays and work anniversaries from employee dates. The event model is extensible, but other event types should not be assumed to be active.')],
				},
				{
					title: t('empleados', 'Simulated events'),
					paragraphs: [t('empleados', 'Coffee visits, meetings, printer trips, roaming, conversations and conflict icons are generated inside the simulation and are not historical records.')],
				},
				{
					title: t('empleados', 'Environmental events'),
					paragraphs: [t('empleados', 'Ambient probabilities can trigger eligible social scenes when location, timing and cooldown conditions align. They describe the virtual environment only.')],
				},
			],
		},
		{
			id: 'emergence',
			number: '10',
			title: t('empleados', 'Emergent behavior'),
			summary: t('empleados', 'No central script choreographs every avatar. Larger patterns appear when many small decisions share the same space.'),
			visual: 'emergence',
			sections: [
				{
					title: t('empleados', 'From local to collective'),
					paragraphs: [t('empleados', 'Destinations, distance, room context, timing and chance are evaluated locally. A queue, gathering or quiet room can therefore appear without being explicitly staged.')],
				},
				{
					title: t('empleados', 'Conflicts'),
					paragraphs: [t('empleados', 'A conflict is a deliberately fictional visual event produced by simulation rules. It does not report, infer or prove a dispute between employees.')],
				},
			],
		},
		{
			id: 'architecture',
			number: '11',
			title: t('empleados', 'Technical architecture'),
			summary: t('empleados', 'The feature separates server data preparation, client-side entity state, simulation rules, physics and canvas rendering.'),
			visual: 'pipeline',
			sections: [
				{
					title: t('empleados', 'Processing pipeline'),
					paragraphs: [t('empleados', 'Data becomes entity state; rules produce intentions; physics resolves positions and interactions; the renderer draws the current frame.')],
				},
				{
					title: t('empleados', 'Runtime boundaries'),
					paragraphs: [t('empleados', 'The simulation loop stays outside Vue reactivity. Vue owns controls and panels, while the canvas keeps its existing runtime until the view is actually destroyed.')],
				},
				{
					title: t('empleados', 'Main modules'),
					bullets: [
						t('empleados', 'Configuration defines rates, limits and interaction types.'),
						t('empleados', 'Layout and role profiles create rooms, anchors and preferences.'),
						t('empleados', 'Physics and social rules update nodes; canvas rendering presents them.'),
					],
				},
			],
		},
		{
			id: 'privacy',
			number: '12',
			title: t('empleados', 'Privacy and limits'),
			summary: t('empleados', 'The most important rule is interpretive: simulated behavior must never be treated as evidence about a person.'),
			warning: t('empleados', 'Do not use Office Simulation to evaluate productivity, attendance, relationships, mood, conduct or performance.'),
			sections: [
				{
					title: t('empleados', 'Explicit limits'),
					bullets: [
						t('empleados', 'Movement is not precise tracking.'),
						t('empleados', 'Energy is not productivity.'),
						t('empleados', 'Affinity is not a real relationship measure.'),
						t('empleados', 'Conversations, groups and conflicts are fictional.'),
					],
				},
				{
					title: t('empleados', 'Responsible use'),
					paragraphs: [t('empleados', 'Treat the feature as an organizational visualization and a software experiment. Decisions about people require appropriate, verified sources outside this simulation.')],
				},
			],
		},
		{
			id: 'vision',
			number: '13',
			title: t('empleados', 'Project vision'),
			summary: t('empleados', 'The long-term idea is to make complex organizational systems understandable through a humane, playful and transparent visual language.'),
			sections: [
				{
					title: t('empleados', 'Design direction'),
					paragraphs: [t('empleados', 'Future additions should remain explainable, optional and visibly separated from factual records. More realism must never mean less clarity about uncertainty.')],
				},
				{
					title: t('empleados', 'Extension rule'),
					paragraphs: [t('empleados', 'New data signals, states or events should document their source, purpose, privacy boundary and visual effect before they become part of the scene.')],
				},
			],
		},
	]

	return { introSteps, chapters }
}
