import { useQuery } from '@tanstack/react-query';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { get } from '../shared/api';

dayjs.extend(relativeTime);

export interface RecentAchievement {
	status_id: number;
	id: number;
	name: string;
	image: string;
	user_id: number;
	user_name: string;
	user_avatar_url: string;
	user_rank: string;
	created: string;
}

interface RecentAchievementsProps {
	initialAchievements: RecentAchievement[];
}

export function RecentAchievements({ initialAchievements }: RecentAchievementsProps)
{
	const query = useQuery<{ recentAchievements: RecentAchievement[] }>({
		queryKey: ['recentAchievements'],
		queryFn: () => get<{ recentAchievements: RecentAchievement[] }>('/sites/recentAchievements'),
		initialData: { recentAchievements: initialAchievements },
		refetchInterval: 60000,
		refetchOnWindowFocus: false,
		retry: false,
	});

	const achievements = query.data.recentAchievements;

	return (
		<div id="recent-achievement-stream">
			{achievements.map(achievement => (
				<div
					key={achievement.status_id}
					className="recent-achievement-row"
				>
					<div className="recent-achievement-icon">
						<a href={`/achievements/view/${achievement.id}`}>
							<img src={`/img/${achievement.image}.png`} width="34px" alt={achievement.name} />
						</a>
					</div>
					<div className="recent-achievement-body">
						<div className="recent-achievement-message">
							<a className="recent-achievement-user-link" href={`/users/view/${achievement.user_id}`}>
								<img src={achievement.user_avatar_url} alt="" className="user-avatar" style={{width: 20, height: 20, borderRadius: '50%', verticalAlign: 'middle', marginRight: 4}} />
								{achievement.user_name}{achievement.user_rank ? ` ${achievement.user_rank}` : ''}
							</a> earned&nbsp;
							<a className="recent-achievement-achievement-link" href={`/achievements/view/${achievement.id}`}>
								<b>{achievement.name}</b>
							</a>
						</div>
						<div className="recent-achievement-time">{dayjs(achievement.created).fromNow()}</div>
					</div>
				</div>
			))}
		</div>
	);
}
