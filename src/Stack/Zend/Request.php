<?php
namespace Stack\Zend;

use Zend\Http\PhpEnvironment\Request as HttpRequest;
use Zend\Stdlib\Parameters;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class Request extends HttpRequest
{
    /**
     * Do not read the superglobals
     */
    public function __construct()
    {
        // PhpEnvironment reads superglobals by default, we do not want this.
    }

    /**
     * Creates a Zend Request from a Symfony Request.
     *
     * @todo _ENV and _FILES.
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return Stack\Zend\Request
     */
    public static function fromSymfony(SymfonyRequest $request)
    {
        $instance = new static();
        $instance->setQuery(new Parameters($request->query->all()));
        $instance->setPost(new Parameters($request->request->all()));
        $instance->setCookies(new Parameters($request->cookies->all()));

        $server = $request->server->all();

        // Empty strings in a default Symfony request, ZF doesn't like that.
        $unsetOnEmpty = array('SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF');
        foreach ($unsetOnEmpty as $unsetMe) {
            if (isset($server[$unsetMe]) && $server[$unsetMe] == '') {
                unset($server[$unsetMe]);
            }
        }
        $instance->setServer(new Parameters($server));

        $instance->setContent($request->getContent());

        return $instance;
    }
}
