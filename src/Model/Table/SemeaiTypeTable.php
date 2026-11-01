<?php

namespace App\Model\Table;

//The types of semeai, which the explanation of a semeai problem tells apart (webroot/js/multipleChoice.js),
//the rows of semeai_type
class SemeaiTypeTable extends AppTable
{
	public const TYPE_NO_EYES_FEW_INSIDE_LIBERTIES = 1;
	public const TYPE_NO_EYES_INSIDE_LIBERTIES = 2;
	public const TYPE_EYE_VS_NO_EYE = 3;
	public const TYPE_SAME_SIZED_BIG_EYES = 4;
	public const TYPE_BIG_EYE_VS_SMALLER_EYE = 5;
	public const TYPE_SMALL_EYE_VS_SMALL_EYE = 6;
}
