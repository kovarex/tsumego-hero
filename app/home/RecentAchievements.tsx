import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { Avatar } from '../shared/Avatar';

dayjs.extend(relativeTime);

export interface RecentAchievement {
	status_id: number;
	id: number;
	name: string;
	image: string;
	user_id: number;
	user_display_name: string;
	user_avatar_url: string;
	user_rank: string;
	created: string;
}

interface RecentAchievementsProps {
	achievements: RecentAchievement[];
}

//The list comes with the page and is not polled: the home page is left within moments,
//and achievements are too rare for a refresh to show anything new.
export function RecentAchievements({ achievements }: RecentAchievementsProps)
{
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
								<Avatar url={achievement.user_avatar_url} fallbackUrl={`/avatar/${achievement.user_id}`} />
								{achievement.user_display_name}{achievement.user_rank ? ` ${achievement.user_rank}` : ''}
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
