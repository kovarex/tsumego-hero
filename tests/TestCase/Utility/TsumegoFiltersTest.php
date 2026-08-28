<?php

App::uses('TsumegoFilters', 'Utility');
App::uses('Preferences', 'Utility');

class TsumegoFiltersTest extends CakeTestCase
{
	public function testCollectionSizeClampedToMaximum(): void
	{
		new ContextPreparator();
		Preferences::set('collection_size', 1500);

		$filters = new TsumegoFilters();
		$this->assertSame(1000, $filters->collectionSize);
	}

	public function testCollectionSizeClampedToMinimum(): void
	{
		new ContextPreparator();
		Preferences::set('collection_size', 5);

		$filters = new TsumegoFilters();
		$this->assertSame(10, $filters->collectionSize);
	}

	public function testCollectionSizeUnchangedWithinRange(): void
	{
		new ContextPreparator();
		Preferences::set('collection_size', 60);

		$filters = new TsumegoFilters();
		$this->assertSame(60, $filters->collectionSize);
	}

	public function testCollectionSizeDefaultsTo200(): void
	{
		new ContextPreparator();
		Preferences::set('collection_size', '');

		$filters = new TsumegoFilters();
		$this->assertSame(200, $filters->collectionSize);
	}

	public function testCollectionSizeClampedFromCookie(): void
	{
		new ContextPreparator();
		$_COOKIE['collection_size'] = '1500';

		$filters = new TsumegoFilters();
		$this->assertSame(1000, $filters->collectionSize);
	}

	public function testEmptyFiltersCalculateCountDoesNotThrow(): void
	{
		// empty() is used e.g. by UsersController to count all public problems
		new ContextPreparator(['tsumego' => ['sets' => [['name' => 'public set', 'num' => '1']]]]);
		$count = TsumegoFilters::empty()->calculateCount();
		$this->assertSame(1, $count);
	}
}
