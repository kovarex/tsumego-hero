<?php
namespace tsumego\customsso\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return ['overall_header_navigation_prepend' => 'disable_logout'];
	}

	public function disable_logout($event)
	{
		// Remove logout link
		$nav = $event['navlinks'];
		foreach ($nav as $i => $link)
		{
			if (!empty($link['S_LOGOUT']))
			{
				unset($nav[$i]);
				continue;
			}

			if (!empty($link['S_REGISTER']))
			{
				unset($nav[$i]);
				continue;
			}

			if (!empty($link['S_LOGIN']))
			{
				unset($nav[$i]);
				continue;
			}
		}
		$event['navlinks'] = $nav;
	}
}
