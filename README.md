# Stack/ZF2

Using Symfony Http Foundation to make use of Stack.

## Why?

Because it can be done. I wanted to investigate how flexible ZF2 is versus SF. It helped me understand the inner workings of Zend Framework.

## Practical use?

A number of things including:
- Gracefully migrate from Zend Framework to Symfony or the other way around, using stack/url-map.
- Easy to write test cases, just forge a Symfony Request and get a Symfony Response back.
- Find architecture flaws in ZF2.

## Todo

- Conversion from Symfony to Zend Request is via toString.
- Find out what to do with Request::getBasePath. Hardcoded usage in some helper. 
  (this is why we need a Zend\Http\PhpEnvironment\Request for now)
- Find out how to handle chdir, probably a setter on our kernel.
- Test cases
- Test compatability other stack components.

## Support

- twitter: https://twitter.com/reenlokum
