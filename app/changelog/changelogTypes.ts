export interface ChangelogEntry
{
	ts: number;
	date: string;
	category: string;
	text: string;
	file: string;
	commit?: string;
}
