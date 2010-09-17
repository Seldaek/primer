<?php

namespace Primer;

class PrimerTest extends \PHPUnit_Framework_TestCase
{
    protected static function openMethod($class, $method)
    {
        $class = new \ReflectionClass($class);
        $method = $class->getMethod($method);
        $method->setAccessible(true);
        return $method;
    }

    protected function createPrimerObject()
    {
        return new Primer(array(
            'options'=>array(),
            'routes'=>array(
                'defaults'=>array()
            )
        ));
    }

    /**
     * @covers Primer\Primer::domainPolicyAllows()
     * @dataProvider domainPolicyAllowsDataProvider()
     */
    public function testDomainPolicyAllows($policy, $routeUrl, $url, $expected)
    {
        $method = self::openMethod('Primer\Primer', 'domainPolicyAllows');
        $primer = $this->createPrimerObject();

        $route = array(
            'domain' => $policy,
            'url' => $routeUrl,
        );
        $this->assertEquals($expected, $method->invokeArgs($primer, array($url, $route)));
    }

    public function domainPolicyAllowsDataProvider()
    {
        return array(
            array('any',        'http://www.test.com/',     'http://bla.foo.cn/',       true),

            array('same-sld',   'http://www.test.com/',     'http://www.test.com/',     true),
            array('same-sld',   'http://www.test.com/',     'http://xxx.test.com/',     true),
            array('same-sld',   'http://www.test.com/',     'http://test.com/',         true),
            array('same-sld',   'http://test.com/',         'http://www.test.com/',     true),
            array('same-sld',   'http://www.test.com/',     'http://www.testy.com/',    false),
            array('same-sld',   'http://www.test.com/',     'http://testy.com/',        false),
            array('same-sld',   'http://test.com/',         'http://test.cn/',          false),

            array('same-tld',   'http://www.test.com/',     'http://www.test.com/',     true),
            array('same-tld',   'http://www.test.com/',     'http://xxx.test.com/',     true),
            array('same-tld',   'http://www.test.com/',     'http://foo.bar.com/foo',   true),
            array('same-tld',   'http://www.test.com/',     'http://www.test.cn/',      false),
            array('same-tld',   'http://www.test.com/',     'http://test.cn/',          false),
            array('same-tld',   'http://www.test.com/',     'http://foo.bar.cn/foo',    false),

            array('same',       'http://www.test.com/',     'http://www.test.com/',     true),
            array('same',       'http://www.test.com/',     'http://xxx.test.com/',     false),
            array('same',       'http://www.test.com/',     'http://yyy.test.com/',     false),
            array('same',       'http://www.test.com/',     'http://www.testy.com/',    false),
            array('same',       'http://www.test.com/',     'http://www.test.cn/',      false),
        );
    }

    /**
     * @covers Primer\Primer::domainPolicyAllows()
     */
    public function testDomainPolicyAllowsAcceptedPolicies()
    {
        $method = self::openMethod('Primer\Primer', 'domainPolicyAllows');
        $primer = $this->createPrimerObject();

        foreach (array('same', 'same-tld', 'same-sld', 'any') as $policy) {
            $route = array(
                'domain' => $policy,
                'url' => 'http://test.com',
            );
            $this->assertTrue($method->invokeArgs($primer, array('http://test.com', $route)));
        }

        try {
            $route['domain'] = 'foo';
            $method->invokeArgs($primer, array('http://test.com', $route));
            $this->fail();
        } catch (\UnexpectedValueException $e) {
        }
    }
}
