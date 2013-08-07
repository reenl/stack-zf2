<?php
namespace Stack;

use Stack\Zend\Request as ZendRequest;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Http\Response as ZendResponse;

class ZendHttpKernel implements HttpKernelInterface, ListenerAggregateInterface
{
    /**
     *
     * @var \Zend\Mvc\Application
     */
    protected $application;

    /**
     *
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();

    /**
     * The Zend\Mvc\Application can be injected here if you wish not to use
     * the ::init function.
     *
     * Make sure your application is bootstrapped.
     *
     * @param \Zend\Mvc\Application $application
     * @return \Stack\ZendHttpKernel
     */
    public function __construct(Application $application = null)
    {
        // Add the application.
        if ($application !== null) {
            $this->setApplication($application);
        }
    }

    /**
     * Set the Zend\Mvc\Application and attach the event listeners.
     *
     * @param \Zend\Mvc\Application $application
     * @return ZendHttpKernel
     */
    public function setApplication(Application $application)
    {
        if ($this->application === $application) {
            // Application unchanged.
            return $this;
        }

        // Unregister from previous application.
        if ($this->application !== null) {
            // Detach from old application.
            $this->detach($this->application->getEventManager());
        }

        // Set the application.
        $this->application = $application;
        $this->attach($application->getEventManager());

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $type
     * @param boolean $catch
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(SymfonyRequest $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $this->setSymfonyRequest($request);

        // Register if exceptions should be caught into the ServiceManager.
        $this->setCatchExceptions($catch);

        // Run the application.
        $this->run();

        return $this->getSymfonyResponse();
    }

    /**
     * Prevent Zend\Mvc\Application from sending the response.
     *
     * @param \Zend\Mvc\MvcEvent $event
     * @return null
     */
    public function onFinish(MvcEvent $event)
    {
        if ($event->getApplication() !== $this->application) {
            // Do nothing if this application is not managed.
            return;
        }
        $event->stopPropagation(true);
    }

    /**
     * Throws exceptions on error events.
     *
     * @param \Zend\Mvc\MvcEvent $event
     * @return null
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function throwException(MvcEvent $event)
    {
        if ($event->getApplication() !== $this->application) {
            // Do nothing if this application is not managed.
            return;
        }

        $sm = $this->application->getServiceManager();
        if ($sm->get('KernelCatchExceptions')) {
            return;
        }

        $event->stopPropagation(true);

        $ex = $event->getParam('exception');
        if ($ex !== null) {
            throw new ServiceUnavailableHttpException(null, null, $ex);
        }

        $error = $event->getError();
        $message = 'Unkown error.';
        if ($error) {
            $message .= ' ('.$error.')';
        }
        $class = '\Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException';

        switch ($error) {
            case Application::ERROR_CONTROLLER_NOT_FOUND:
            case Application::ERROR_CONTROLLER_INVALID:
            case Application::ERROR_ROUTER_NO_MATCH:
                $message = '404 not found: '.$error;
                $class = '\Symfony\Component\HttpKernel\Exception\NotFoundHttpException';
                break;
        }
        throw new $class($message);
    }

    /**
     * Run the application.
     *
     * @return null
     */
    public function run()
    {
        $this->application->run();
    }

    /**
     * Sets the request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return null
     */
    protected function setSymfonyRequest(SymfonyRequest $request)
    {
        $zendRequest = self::createZendRequest($request);

        $serviceManager = $this->application->getServiceManager();
        $serviceManager->setAllowOverride(true);
        $serviceManager->setService('Request', $zendRequest);
        $serviceManager->setAllowOverride(false);

        $this->application->getMvcEvent()->setRequest($zendRequest);
    }

    /**
     * Adds a value to the ServiceManager to indicate if an exception must be
     * thrown or caught.
     *
     * @param boolean $catch
     * @return null
     */
    protected function setCatchExceptions($catch)
    {
        $serviceManager = $this->application->getServiceManager();
        $serviceManager->setAllowOverride(true);
        $serviceManager->setService('KernelCatchExceptions', $catch);
        $serviceManager->setAllowOverride(false);
    }

    /**
     * Gets the response from the service manager and convert it to Symfony.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getSymfonyResponse()
    {
        $zendResponse = $this->application->getResponse();
        return self::createSymfonyResponse($zendResponse);
    }

    /**
     * Converts a symfony request to a zend request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Zend\Http\PhpEnvironment\Request
     */
    protected static function createZendRequest(SymfonyRequest $request)
    {
        return ZendRequest::fromSymfony($request);
    }

    /**
     * Converts a zend response to a symfony response.
     *
     * @param \Zend\Http\Response $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected static function createSymfonyResponse(ZendResponse $response)
    {
        $content = $response->getContent();
        $status = $response->getStatusCode();
        $zendHeaders = $response->getHeaders();
        $headers = array();
        foreach ($zendHeaders as $header) {
            $name = $header->getFieldName();
            $headers[$name] = $header->getFieldValue();
        }
        return new SymfonyResponse($content, $status, $headers);
    }

    /**
     * Attach the listeners.
     *
     * @param \Zend\EventManager\EventManagerInterface $events
     * @return null
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, array($this, 'onFinish'));
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'throwException'));
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, array($this, 'throwException'));
    }

    /**
     * Detach the listeners.
     *
     * @param \Zend\EventManager\EventManagerInterface $events
     * @return null
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }
}
