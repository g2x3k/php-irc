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
|   > timers module
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

/*
 * Redesigned 12/22/04... Yes... this file sucks.  I basically am going to
 * do a few things here.  I have been stuck on this file for the past 2 weeks,
 * trying to come up with some ideas to handle the hopeless situation which
 * faced me.  With the new queue system, this timer class became very interesting
 * to handle.  I had to decide how I would add proc queues to the queue class
 * to handle specific timers in this file.  Say, for instance, that a timer
 * is added, and then another is added that has a shorter time than that one.
 * The first one will have a proc queue added into the queue class, but then
 * the second one will have to add another proc queue.  But hold on a second,
 * the way this works right now, after a timer is done running, the next one
 * is added to the process queue.  I handled this by keeping track of how
 * many processes were in the queue, and didn't add one if there were more than
 * one, and the call to setCurrentTimer was from handle().  This worked, unless
 * you have a timer that repeats.  Then the problem comes in, as it will not be
 * added to the proc queue until the next timer is complete.  To just handle 
 * this problem, I'm just going to add a proc to the queue for every timer
 * that is added, and then every timer will have a queue in the procqueue.
 * that way, we don't have to worry about anything.
 *
 * Also, I added a "timerStack" so that I could have reserved names and what
 * not.  Each timer gets a unique name or ID, and that is added to the stack,
 * as well as sorted into the timerList.
 *
 * Okay, way that timers are handled... has changed, if you want a timer to 
 * repeat, you must return true from the timer, runOnce was removed.
 *
 * Ooohh oohh ohh! Idea.  screw linked lists and shit.  I'll just add each
 * timer to the proc queue, and then have them call handle() with the timer
 * referenced! This solves all problems, and is incredibly more efficient!
 * This officially takes the last linked list out of my bot.  I have NO IDEA
 * why I even used them in the first place, as php already has associative arrays
 * which are a lot better! GEEZ!
 */

class timers {

	//Local variables
	private $timerStack = array();	//list of all timers indexed by name

	//External Classes
	private $procQueue;
	private $socketClass;
	private $ircClass;
	
	//Private list of reserved php-irc timer names (please do not
	//use these names)
	private $reserved = array(	"listening_timer_[0-9]*",
								"check_nick_timer",
								"check_channels_timer",
								"check_ping_timeout_timer",
						);

	public function __construct()
	{
		$this->time = time();
		$this->timerStack = array();
	}

	public function setSocketClass($class)
	{
		$this->socketClass = $class;
	}

	public function setIrcClass($class)
	{
		$this->ircClass = $class;
	}

	public function setProcQueue($class)
	{
		$this->procQueue = $class;
	}

	public static function getMicroTime()
	{
		return microtime(true);
	}

	public function getTimers()
	{
		return $this->timerStack;
	}

	public function handle($timer)
	{
		$microTime = self::getMicroTime();

		if (!isset($this->timerStack[$timer->name]))
		{
			return false;
		}

		if ($this->timerStack[$timer->name] !== $timer)
		{
			return false;
		}

		$timer->lastTimeRun = $microTime;
		$timer->nextRunTime = $microTime + $timer->interval;

		if ($timer->class != null)
		{
			$theFunc = $timer->func;
			$status = $timer->class->$theFunc($timer->args);
		}
		else
		{
			$theFunc = $timer->func;
			$status = $theFunc($timer->args);
		}

		if ($status != true)
		{
			$this->removeTimer($timer->name);
		}
		else
		{
			$this->procQueue->addQueue($this->ircClass, $this, "handle", $timer, $timer->interval);
		}

		return false;
	}

	public function removeAllTimers()
	{
		foreach ($this->timerStack AS $timer)
		{
			$this->removeTimer($timer->name);
		}
	}


	public function addTimer($name, $class, $function, $args, $interval, $runRightAway = false)
	{
		if (trim($name) == "")
		{
			return false;
		}

		if (isset($this->timerStack[$name]))
		{
			return false;
		}

		$newTimer = new timer;

		$newTimer->name = $name;
		$newTimer->class = $class;
		$newTimer->func = $function;
		$newTimer->args = $args;
		$newTimer->interval = $interval;
		$newTimer->removed = false;

		if ($runRightAway == false)
		{
			$newTimer->lastTimeRun = $this->getMicroTime();
			$newTimer->nextRunTime = $this->getMicroTime() + $interval;
			$tInterval = $interval;
		}
		else
		{
			$newTimer->lastTimeRun = 0;
			$newTimer->nextRunTime = $this->getMicroTime();
			$tInterval = 0;
		}

		$this->procQueue->addQueue($this->ircClass, $this, "handle", $newTimer, $tInterval);

		$this->timerStack[$newTimer->name] = $newTimer;

		return $name;
	}

	/* Remove the current timer from both the list and stack, changed in 2.1.2, can only call by
	 * timer name now.
	 */
	public function removeTimer($name)
	{
		if (!isset($this->timerStack[$name]))
		{
			return false;
		}

		//Set removed flag,
		$this->timerStack[$name]->removed = true;

		//Remove from stack
		unset($this->timerStack[$name]->args);
		unset($this->timerStack[$name]->class);
		unset($this->timerStack[$name]);

		return true;
	}

}

?>
