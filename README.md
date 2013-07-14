# Stack/ZF2

Using Symfony Http Foundation to make use of Stack.

## Installation

If you install zend framework using composer simply add the require:
`"reenl/stack-zf2": "dev-master"`. And update.

After that you probably want to change `public/index.php` to use the HttpKernel,
in order to do that you can copy `public_example/index.php` from this
repository.

However it would make more sense for you to create your own stack :) See:
http://stackphp.com/

If you don't use composer, start using it.

## Why?

Because it can be done. I wanted to investigate how flexible ZF2 is versus SF.
It helped me understand the inner workings of Zend Framework.

## Practical use?

A number of things including:
- Gracefully migrate from Zend Framework to Symfony or the other way around,
  using stack/url-map.
- Ability to use (hopefully all) the stack middlewares.
- Easy to write test cases, just forge a Symfony Request and get a
  Symfony Response back.
- Find architecture flaws in ZF2.

## Todo

- Conversion from Symfony to Zend Request is via toString.
- Find out what to do with Request::getBasePath. Hardcoded usage in some helper.
  (this is why we need a Zend\Http\PhpEnvironment\Request for now)
- Find out how to handle chdir, probably a setter on our kernel.
- Test cases
- Test compatibility other stack components.
- Try to make ZF throw exceptions instead of an error page when $catch is true.

## Support

- Open a ticket
- twitter: https://twitter.com/reenlokum
