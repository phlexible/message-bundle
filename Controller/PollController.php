<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\MessageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Poll controller
 *
 * @author Stephan Wentz <sw@brainbits.net>
 * @Route("/messages/poll")
 */
class PollController extends Controller
{
    /**
     * Poll Action
     *
     * @return JsonResponse
     * @Route("", name="messages_poll")
     */
    public function indexAction()
    {
        $messages = array();

        $data = array();
        foreach ($this->get('portlets') as $portlet) {
            $data[$portlet->getId()] = $portlet->getData();
        }

        $message = new PollerMessage();
        $message->type     = 'start';
        $message->event    = 'update';
        $message->uid      = $this->getSecurityContext()->getUser()->getId();
        $message->msg      = null;
        $message->data     = $data;
        $message->objectID = null;
        $message->ts       = date('Y-m-d H:i:s');

        $messages[] = $message;

        $pollSession = new \Zend_Session_Namespace('poll');

        if (!isset($pollSession->lastPoll))
        {
            $pollSession->lastPoll = date('Y-m-d H:i:s');
        }

        $lastMessages = MWF_Core_Messages_Message_Query::getByFilter(array(array(array(
            'key'   => MWF_Core_Messages_Filter::CRITERIUM_START_DATE,
            'value' => $pollSession->lastPoll
        ))), $this->getSecurityContext()->getUser()->getId(), 5);

        foreach ($lastMessages as $lastMessage)
        {
            try
            {
                $user = MWF_Core_Users_User_Peer::getByUserID($lastMessage['create_uid']);
            }
            catch (Exception $e)
            {
                $user = MWF_Core_Users_User_Peer::getSystemUser();
            }

            $message = new MWF_Core_Messages_Frontend_Message();
            $message->type     = 'message';
            $message->event    = 'message';
            $message->uid      = $lastMessage['create_uid'];
            $message->msg      = $lastMessage['subject'] . ' [' . $user->getUsername() . ']';
            $message->data     = array();
            $message->objectID = null;
            $message->ts       = $lastMessage['created_at'];

            if ($lastMessage['created_at'] > $pollSession->lastPoll)
            {
                $pollSession->lastPoll = $lastMessage['created_at'];
            }

            $messages[] = $message;
        }

        return new JsonResponse($messages);
    }
}
