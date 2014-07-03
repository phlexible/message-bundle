<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\MessageBundle\Controller;

use Phlexible\Bundle\MessageBundle\Criteria\Criteria;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Messages controller
 *
 * @author Stephan Wentz <sw@brainbits.net>
 * @Route("/messages/messages")
 */
class MessagesController extends Controller
{
    /**
     * List messages
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @Route("", name="messages_messages")
     */
    public function listAction(Request $request)
    {
        $limit = $request->get('limit', 25);
        $start = $request->get('start', 0);
        $sort = $request->get('sort', 'createdAt');
        $dir = $request->get('dir', 'DESC');
        $filter = $request->get('filter', null);

        if ($filter) {
            $filter = json_decode($filter, true);
        }

        if (!is_array($filter)) {
            $filter = array();
        }

        $messageManager = $this->get('phlexible_message.message_manager');

        $priorityList = $messageManager->getPriorityNames();
        $typeList = $messageManager->getTypeNames();

        $priorityFilter = array();
        $typeFilter = array();
        $channelFilter = array();
        $resourceFilter = array();

        $criteria = new Criteria();
        foreach ($filter as $key => $value) {
            if ($key == 'subject' && !empty($value)) {
                $criteria->addRaw(Criteria::CRITERIUM_SUBJECT_LIKE, $value);
            } elseif ($key == 'text' && !empty($value)) {
                $criteria->addRaw(Criteria::CRITERIUM_BODY_LIKE, $value);
            } elseif (substr($key, 0, 9) == 'priority_') {
                $priorityFilter[] = substr($key, 9);
            } elseif (substr($key, 0, 5) == 'type_') {
                $typeFilter[] = substr($key, 5);
            } elseif (substr($key, 0, 10) == 'channel_') {
                $channelFilter[] = substr($key, 8);
            } elseif (substr($key, 0, 9) == 'resource_') {
                $resourceFilter[] = substr($key, 9);
            } elseif ($key == 'date_after' && !empty($value)) {
                $criteria->addRaw(Criteria::CRITERIUM_START_DATE, $value);
            } elseif ($key == 'date_before' && !empty($value)) {
                $criteria->addRaw(Criteria::CRITERIUM_END_DATE, $value);
            }
        }

        if (count($priorityFilter)) {
            $criteria->addRaw(
                Criteria::CRITERIUM_PRIORITY_IN,
                implode(',', $priorityFilter)
            );
        }

        if (count($typeFilter)) {
            $criteria->addRaw(
                Criteria::CRITERIUM_TYPE_IN,
                implode(',', $typeFilter)
            );
        }

        if (count($channelFilter)) {
            $criteria->addRaw(
                Criteria::CRITERIUM_CHANNEL_IN,
                implode(',', $channelFilter)
            );
        }

        if (count($resourceFilter)) {
            $criteria->addRaw(
                Criteria::CRITERIUM_RESOURCE_IN,
                implode(',', $resourceFilter)
            );
        }

        $count = $messageManager->countByCriteria($criteria);
        $messages = $messageManager->findByCriteria($criteria, array($sort => $dir), $limit, $start);

        $data = array();
        foreach ($messages as $message) {
            $data[] = array(
                'subject'   => $message->getSubject(),
                'body'      => nl2br($message->getBody()),
                'priority'  => $priorityList[$message->getPriority()],
                'type'      => $typeList[$message->getType()],
                'channel'   => $message->getChannel(),
                'resource'  => $message->getResource(),
                'user'      => $message->getUser(),
                'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
            );
        }

        return new JsonResponse(array(
            'totalCount' => $count,
            'messages'   => $data,
            'facets'     => $messageManager->getFacetsByCriteria($criteria),
        ));
    }

    /**
     * List filter values
     *
     * @return JsonResponse
     * @Route("/facets", name="messages_messages_facets")
     */
    public function facetsAction()
    {
        $messageManager = $this->get('phlexible_message.message_manager');

        $filterSets = $messageManager->getFacets();
        $priorityList = $messageManager->getPriorityNames();
        $typeList = $messageManager->getTypeNames();

        $priorities = array();
        arsort($filterSets['priorities']);
        foreach ($filterSets['priorities'] as $priority) {
            $priorities[] = array('id' => $priority, 'title' => $priorityList[$priority]);
        }

        $types = array();
        arsort($filterSets['types']);
        foreach ($filterSets['types'] as $key => $type) {
            $types[] = array('id' => $type, 'title' => $typeList[$type]);
        }

        $channels = array();
        sort($filterSets['channels']);
        foreach ($filterSets['channels'] as $channel) {
            $channels[] = array('id' => $channel, 'title' => $channel ? : '(no channel)');
        }

        $resources = array();
        sort($filterSets['resources']);
        foreach ($filterSets['resources'] as $resource) {
            $resources[] = array('id' => $resource, 'title' => $resource ? : '(no resource)');
        }

        return new JsonResponse(array(
            'priorities' => $priorities,
            'types'      => $types,
            'channels'   => $channels,
            'resources'  => $resources,
        ));
    }
}
