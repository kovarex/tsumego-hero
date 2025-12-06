class Rating
{
	static getReadableRank(rank)
	{
		if (rank <= 30)
		return(31 - rank) + 'k';

		return (rank - 30) + 'd';
	}

	static getRankFromRating(rating)
	{
		if (rating < 2750)
			return Math.floor(Math.max((rating + 1050) / 100, 1));

		return Math.floor((rating - 2750) / 30) + 38;
	}

	static getReadableRankFromRating(rating)
	{
		return Rating.getReadableRank(Rating.getRankFromRating(rating));
	}

	static getRankMinimalRating(rank)
	{
		if (rank <= 38)
			return 100 * rank - 1050.0;
		return (rank - 38) * 30 + 2750.0;
	}
}
