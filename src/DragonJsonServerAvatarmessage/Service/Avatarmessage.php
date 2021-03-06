<?php
/**
 * @link http://dragonjsonserver.de/
 * @copyright Copyright (c) 2012-2014 DragonProjects (http://dragonprojects.de/)
 * @license http://license.dragonprojects.de/dragonjsonserver.txt New BSD License
 * @author Christoph Herrmann <developer@dragonprojects.de>
 * @package DragonJsonServerAvatarmessage
 */

namespace DragonJsonServerAvatarmessage\Service;

/**
 * Serviceklasse zur Verwaltung von Avatarnachrichten
 */
class Avatarmessage
{
	use \DragonJsonServer\ServiceManagerTrait;
	use \DragonJsonServer\EventManagerTrait;
	use \DragonJsonServerDoctrine\EntityManagerTrait;
	
	/**
	 * Erstellt eine Avatarnachricht zu einem anderen Avatar
	 * @param \DragonJsonServerAvatar\Entity\Avatar $from_avatar
	 * @param integer $to_avatar_id
	 * @param string $subject
	 * @param string $content
	 * @return Avatarmessage
	 */
	public function createAvatarmessage(\DragonJsonServerAvatar\Entity\Avatar $from_avatar, $to_avatar_id, $subject, $content)
	{
		$serviceManager = $this->getServiceManager();
		
		$to_avatar = $serviceManager->get('\DragonJsonServerAvatar\Service\Avatar')->getAvatarByAvatarId($to_avatar_id);
		if ($from_avatar->getGameroundId() != $to_avatar->getGameroundId()) {
    		throw new \DragonJsonServer\Exception(
    			'gameround_id not match',
    			['from_avatar' => $from_avatar->toArray(), 'to_avatar' => $to_avatar->toArray()]
    		);
		}
		$avatarmessage = (new \DragonJsonServerAvatarmessage\Entity\Avatarmessage())
			->setFromAvatar($from_avatar)
			->setToAvatar($to_avatar)
			->setSubject($subject)
			->setContent($content);
		$this->getServiceManager()->get('\DragonJsonServerDoctrine\Service\Doctrine')->transactional(function ($entityManager) use ($avatarmessage) {
			$entityManager->persist($avatarmessage);
			$entityManager->flush();
			$this->getEventManager()->trigger(
				(new \DragonJsonServerAvatarmessage\Event\CreateAvatarmessage())
					->setTarget($this)
					->setAvatarmessage($avatarmessage)
			);
		});
		return $this;
	}
	
	/**
	 * Erstellt eine Systemnachricht zu einem Avatar
	 * @param integer $to_avatar_id
	 * @param string $subject
	 * @param string $content
	 * @return Avatarmessage
	 */
	public function createSystemmessage($to_avatar_id, $subject, $content)
	{
		$serviceManager = $this->getServiceManager();
		
		$avatarmessage = (new \DragonJsonServerAvatarmessage\Entity\Avatarmessage())
			->setToAvatar($serviceManager->get('\DragonJsonServerAvatar\Service\Avatar')->getAvatarByAvatarId($to_avatar_id))
			->setSubject($subject)
			->setContent($content)
			->setFromState('delete');
		$this->getServiceManager()->get('\DragonJsonServerDoctrine\Service\Doctrine')->transactional(function ($entityManager) use ($avatarmessage) {
			$entityManager->persist($avatarmessage);
			$entityManager->flush();
			$this->getEventManager()->trigger(
				(new \DragonJsonServerAvatarmessage\Event\CreateAvatarmessage())
					->setTarget($this)
					->setAvatarmessage($avatarmessage)
			);
		});
		return $this;
	}
	
	/**
	 * Entfernt die übergebene Avatarnachricht
	 * @param \DragonJsonServerAvatarmessage\Entity\Avatarmessage $avatarmessage
	 * @return Avatarmessage
	 */
	public function removeAvatarmessage(\DragonJsonServerAvatarmessage\Entity\Avatarmessage $avatarmessage)
	{
		$this->getServiceManager()->get('\DragonJsonServerDoctrine\Service\Doctrine')->transactional(function ($entityManager) use ($avatarmessage) {
			$this->getEventManager()->trigger(
				(new \DragonJsonServerAvatarmessage\Event\RemoveAvatarmessage())
					->setTarget($this)
					->setAvatarmessage($avatarmessage)
			);
			$entityManager->remove($avatarmessage);
			$entityManager->flush();
		});
		return $this;
	}
	
	/**
	 * Entfernt alle Avatarnachrichten zu und von der AvatarID
	 * @param integer $avatar_id
	 * @return Avatarmessage
	 */
	public function removeAvatarmessagesByAvatarId($avatar_id)
	{
		$entityManager = $this->getEntityManager();
		
		$avatarmessages = $entityManager
			->createQuery('
				SELECT avatarmessage FROM \DragonJsonServerAvatarmessage\Entity\Avatarmessage avatarmessage
				WHERE 
					avatarmessage.from_avatar = :avatar_id
					OR
					avatarmessage.to_avatar = :avatar_id
			')
			->execute(['avatar_id' => $avatar_id]);
		foreach ($avatarmessages as $avatarmessage) {
			$this->removeAvatarmessage($avatarmessage);
		}
	}
	
	/**
	 * Gibt alle Avatarnachrichten zum aktuellen Avatar zurück
	 * @param integer $avatar_id
	 * @return array
	 */
	public function getInbox($avatar_id)
	{
		$entityManager = $this->getEntityManager();
		
		return $entityManager
			->createQuery("
				SELECT avatarmessage FROM \DragonJsonServerAvatarmessage\Entity\Avatarmessage avatarmessage
				WHERE 
					avatarmessage.to_avatar = :to_avatar_id
					AND
					avatarmessage.to_state IN ('new', 'read')
			")
			->execute(['to_avatar_id' => $avatar_id]);
	}
	
	/**
	 * Gibt alle Avatarnachrichten vom aktuellen Avatar zurück
	 * @param integer $avatar_id
	 * @return array
	 */
	public function getOutbox($avatar_id)
	{
		$entityManager = $this->getEntityManager();
		
		return $entityManager
			->getRepository('\DragonJsonServerAvatarmessage\Entity\Avatarmessage')
		    ->findBy(['from_avatar' => $avatar_id, 'from_state' => 'read']);
	}
	
	/**
	 * Gibt die Avatarnachricht mit der übergebenen AvatarmessageID zurück
	 * @param integer $avatarmessage_id
	 * @return \DragonJsonServerAvatarmessage\Entity\Avatarmessage
	 */
	public function getAvatarmessageByAvatarmessageId($avatarmessage_id)
	{
		$entityManager = $this->getEntityManager();

		$avatarmessage = $entityManager->find('\DragonJsonServerAvatarmessage\Entity\Avatarmessage', $avatarmessage_id);
		if (null === $avatarmessage) {
			throw new \DragonJsonServer\Exception('invalid avatarmessage_id', ['avatarmessage_id' => $avatarmessage_id]);
		}
		return $avatarmessage;
	}
	
	/**
	 * Gibt die Avatarnachrichten für den Avatar und die Clientmessages zurück
	 * @param \DragonJsonServerAvatar\Entity\Avatar $avatar
	 * @param \DragonJsonServer\Event\Clientmessages $eventClientmessages
	 * @return array
	 */
	public function getAvatarmessagesByEventClientmessages(\DragonJsonServerAvatar\Entity\Avatar $avatar, 
														   \DragonJsonServer\Event\Clientmessages $eventClientmessages)
	{
		$entityManager = $this->getEntityManager();

		return $entityManager
			->createQuery("
				SELECT avatarmessage FROM \DragonJsonServerAvatarmessage\Entity\Avatarmessage avatarmessage
				WHERE
					avatarmessage.to_avatar = :to_avatar_id
					AND
					avatarmessage.to_state = 'new'
					AND
					avatarmessage.created >= :from AND avatarmessage.created < :to 
			")
			->execute([
				'to_avatar_id' => $avatar->getAvatarId(), 
				'from' => $eventClientmessages->getFrom(), 
				'to' => $eventClientmessages->getTo(),
			]);
	}
	
	/**
	 * Aktualisiert die übergebene Avatarnachricht in der Datenbank
	 * @param \DragonJsonServerAvatarmessage\Entity\Avatarmessage $avatarmessage
	 * @return Avatarmessage
	 */
	public function updateAvatarmessage(\DragonJsonServerAvatarmessage\Entity\Avatarmessage $avatarmessage)
	{
		$entityManager = $this->getEntityManager();
	
		$entityManager->persist($avatarmessage);
		$entityManager->flush();
		return $this;
	}
	
	/**
	 * Setzt den Status der Avatarnachricht auf gelesen 
	 * @param integer $avatar_id
	 * @param integer $avatarmessage_id
	 * @return Avatarmessage
	 */
	public function readAvatarmessage($avatar_id, $avatarmessage_id)
	{
		$avatarmessage = $this->getAvatarmessageByAvatarmessageId($avatarmessage_id);
		if ($avatarmessage->getToAvatar()->getAvatarId() == $avatar_id) {
			if ('new' != $avatarmessage->getToState()) {
				throw new \DragonJsonServer\Exception(
	    			'state already read or delete',
	    			['avatarmessage_id' => $avatarmessage_id]
	    		);
			}
			$avatarmessage->setToState('read');
		} else {
			throw new \DragonJsonServer\Exception(
    			'avatar_id not match',
    			['avatar_id' => $avatar_id, 'avatarmessage_id' => $avatarmessage_id]
    		);
		}
		$this->updateAvatarmessage($avatarmessage);
		return $this;
	}
	
	/**
	 * Setzt den Status der Avatarnachricht auf gelöscht 
	 * @param integer $avatar_id
	 * @param integer $avatarmessage_id
	 * @return Avatarmessage
	 */
	public function deleteAvatarmessage($avatar_id, $avatarmessage_id)
	{
		$avatarmessage = $this->getAvatarmessageByAvatarmessageId($avatarmessage_id);
		if ($avatarmessage->getFromAvatar()->getAvatarId() == $avatar_id) {
			if ('delete' == $avatarmessage->getFromState()) {
				throw new \DragonJsonServer\Exception(
	    			'state already delete',
	    			['avatarmessage_id' => $avatarmessage_id]
	    		);
			}
			$avatarmessage->setFromState('delete');
		} elseif ($avatarmessage->getToAvatar()->getAvatarId() == $avatar_id) {
			if ('delete' == $avatarmessage->getToState()) {
				throw new \DragonJsonServer\Exception(
	    			'state already delete',
	    			['avatarmessage_id' => $avatarmessage_id]
	    		);
			}
			$avatarmessage->setToState('delete');
		} else {
			throw new \DragonJsonServer\Exception(
    			'avatar_id not match',
    			['avatar_id' => $avatar_id, 'avatarmessage_id' => $avatarmessage_id]
    		);
		}
		if ('delete' == $avatarmessage->getFromState() && 'delete' == $avatarmessage->getToState()) {
			$this->removeAvatarmessage($avatarmessage);
		} else {
			$this->updateAvatarmessage($avatarmessage);
		}
		return $this;
	}
}
