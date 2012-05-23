<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC v2.2.1 Service Release
|   ========================================================
|   by Manick
|   (c) 2001-2005 by http://www.phpbots.org/
|   Contact: manick@manekian.com
|   irc: #manekian@irc.rizon.net
|   ========================================
+---------------------------------------------------------------------------
|   > queue module
|   > Module written by Manick
|   > Module Version Number: 2.2.0
+---------------------------------------------------------------------------
|   > This program is free software; you can redistribute it and/or
|   > modify it under the terms of the GNU General Public License
|   > as published by the Free Software Foundation; either version 2
|   > of the License, or (at your option) any later version.
|   >
|   > This program is distributed in the hope that it will be useful,
|   > but WITHOUT ANY WARRANTY; without even the implied warranty of
|   > MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
|   > GNU General Public License for more details.
|   >
|   > You should have received a copy of the GNU General Public License
|   > along with this program; if not, write to the Free Software
|   > Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
+---------------------------------------------------------------------------
|   Changes
|   =======-------
|   > If you wish to suggest or submit an update/change to the source
|   > code, email me at manick@manekian.com with the change, and I
|   > will look to adding it in as soon as I can.
+---------------------------------------------------------------------------
*/

/* This module is my response to a big problem.  PHP-IRC, on idling, would use
 * 2.0% or more of the CPU on a 500mhz machine.  This annoyed me, and I decided
 * to do something about it.  So, I changed the way that PHP-IRC works, from a
 * "round-robin" approach to an "interrupt" type approach.  Whenever something
 * happens, say, new data is read from a socket, a process Queue is added with
 * a pointer to the function that reads input for that socket; or for
 * another example, if a file transfer is in effect, and new data is read from 
 * that socket, but we also have a dcc chat going, we will only handle data for
 * the file transfer, instead of wasting CPU cycles on the dcc chat, like is
 * currently done in <=2.1.1.  I've learned a bit more about timers and socket
 * timeouts since then.  Also, we have to handle timers in a different way now.
 * There will still be a "timers" class, for re-occuring processes, but the
 * next timer is inserted into this process queue, and the callback for the timer
 * will be a pointer to the timer class which handles the timer.  Then, the timer
 * class will do the appropriate stuff for the timer, and then add the next timer
 * into the process Queue.  --Manick
 *
 * P.S.; I never knew this would change so much code... I figured adding in select()
 * timeouts would be a piece of cake... until I realized that my whole framework
 * was incompatible with the idea.  What a pain in the ass -_-.
 */
 
 /* Module Written 11/30/04 by Manick */

class processQueue {

	private $numQueued;
	private $queuedItems;
	private $currProc;

	function __construct()
	{
		$this->queuedItemsArray = array();
		$this->queuedItems = NULL;
		$this->currProc = NULL;
		$this->numQueued = 0;
	}

	public function getNumQueued()
	{
		return $this->numQueued;
	}

	public static function getMicroTime()
	{
		return microtime(true);
	}

	/* Only allow removal of entire irc class (as in we shut down the bot.. otherwise, we don't
	   want to deal with shutting down specific queues during the queueing process.  Have the callbacks
	   handle that themselves.  We only have to worry when the callbacks won't exist anymore, as when an
	   irc bot is shut down, and the ircclass is discarded
	*/
	public function removeOwner($class)
	{
		$next = NULL;

		for ($queue = $this->queuedItems; $queue != NULL; )
		{
			$next = $queue->next;

			if ($queue->owner === $class)
			{
				$this->removeQueue($queue);
			}

			$queue = $next;

		}
	}

	/* Remove reference to queued item, let PHP5 do the rest */
	private function removeQueue($item)
	{
		if ($item->prev == NULL)
		{
			$this->queuedItems = $item->next;

			if ($item->next != NULL)
			{
				$item->next->prev = NULL;
			}
		}
		else
		{
			$item->prev->next = $item->next;

			if ($item->next != NULL)
			{
				$item->next->prev = $item->prev;
			}
		}
		
		$item->removed = true;

		unset($item->args);
		unset($item->owner);
		unset($item->callBack_class);
        unset($item->next);
        unset($item->prev);

		unset($item);

		$this->numQueued--;
	}

	/* Add an item to the process queue */
	public function addQueue($owner, $class, $function, $args, $nextRunTime)
	{
//		echo "Queue Added: $function with $nextRunTime\n";

		if ($function == "" || $function == NULL)
		{
			return false;
		}

		if (!is_object($class))
		{
			$class = null;
		}

		$nextRunTime = floatval($nextRunTime);

		$queue = new queueItem;

		$queue->args = $args;
		$queue->owner = $owner;
		$queue->removed = false;
		$queue->callBack_class = $class;
		$queue->callBack_function = $function;
		$queue->nextRunTime = self::getMicroTime() + $nextRunTime;

		//Now insert as sorted into queue

		$prev = NULL;

		for ($item = $this->queuedItems; $item != NULL; $item = $item->next)
		{
			if ($queue->nextRunTime < $item->nextRunTime)
			{
				break;
			}

			$prev = $item;
		}

		if ($item == NULL)
		{
			if ($prev == NULL)
			{
				$queue->next = NULL;
				$queue->prev = NULL;
				$this->queuedItems = $queue;
			}
			else
			{
				$queue->next = NULL;
				$queue->prev = $prev;
				$prev->next = $queue;
			}
		}
		else
		{
			if ($item->prev == NULL)
			{
				$queue->next = $this->queuedItems;
				$queue->prev = NULL;

				$item->prev = $queue;
				$this->queuedItems = $queue;
			}
			else
			{
				$queue->next = $item;
				$queue->prev = $item->prev;

				$item->prev = $queue;
				$queue->prev->next = $queue;
			}
		}

		//Okay, we're inserted, return true;
		
		$this->numQueued++;

		return true;
	}
	
	public function displayQueue()
	{
		//Used for debug
		echo "Current Time: " . self::getMicroTime() . "\n";

		echo "\n\n";
		for ($i = $this->queuedItems; $i != NULL; $i = $i->next)
		{
			echo $i->callBack_function . "-" . $i->nextRunTime . "\n";
			echo "---" . "Prev: " . $i->prev . " Next: " . $i->next . " Me: " . $i . "\n";
		}
		echo "\n\n";
	}

	/* Handle the process queue, return the time until the next item */
	public function handle()
	{
		// Handle all items with $queue->nextRunTime < getMicroTime(), then return with time until next item must
		// be run

		// Populate a runQueue with all current items that need to be run.  We need to do this because some of these
		// callback functions might add another process to the queue, and if the runtime is < 0, we would run that item
		// instead of timing out before we do.  If we have something like a file transfer, this could be a bad thing.
		$runQueue = array();

		$time = self::getMicroTime();

		for ($item = $this->queuedItems; $item != NULL; $item = $item->next)
		{
			if ($item->nextRunTime <= $time)
			{
				$runQueue[] = $item;
			}
			else
			{
				break;
			}
		}

		//Okay, now run each item.

		foreach ($runQueue AS $index => $item)
		{
			if (!is_object($item) || $item->removed === true)
			{
				if (is_object($item))
				{
					unset($item);
				}
				continue;
			}

			self::handleQueueItem($item);
		}

		unset($runQueue);

		//Return time until next item needs to be run, or true if there are no queued items
		//Hmm, true returned here, means we'll just sleep for like an hour or something until data
		//is recieved from the sockets, because we have no active timers
		if ($this->queuedItems == null)
		{
			return true;
		}

		//Get new time
		$time = self::getMicroTime();

		$timeTillNext = $this->queuedItems->nextRunTime - $time;

		if ($timeTillNext < 0)
		{
			$timeTillNext = 0;
		}

		//When zero is returned, we'll always sleep at least 50000 usec in the socket class anyway
		return $timeTillNext;

	}

	/* Specific function to deal with queued items */
	private function handleQueueItem($item)
	{
		$this->currTimer = $item;

		$class = $item->callBack_class;
		$func = $item->callBack_function;

		//Call the callback function!  Now the callback function will check all possible triggers,
		//such as socket input, etc, and add new queued items if it needs more processing/other processing

		if ($class == null)
		{
			$status = $func($item->args);
		}
		else
		{
			$status = $class->$func($item->args);
		}

		//If true is returned from the function, then keep the bitch in the queue.  This is useful when a
		//function has not completed processing (i.e., irc->connection waiting on socket class to return
		//the fact that its connected.

		if ($item->removed !== true && $status !== true)
		{
			self::removeQueue($item);
		}

	}

}

?>
