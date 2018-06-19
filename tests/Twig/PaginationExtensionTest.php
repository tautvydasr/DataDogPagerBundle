<?php

namespace Test\Twig;

use DataDog\PagerBundle\Pagination;
use DataDog\PagerBundle\Twig\PaginationExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Twig_Environment;

class PaginationExtensionTest extends TestCase
{
    const ROUTE_NAME = 'route';
    const GENERATED_URL = 'generated';
    
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Twig_Environment
     */
    private $twig;

    /**
     * @var Pagination
     */
    private $pagination;

    /**
     * @var PaginationExtension
     */
    private $extension;

    protected function setUp()
    {
        parent::setUp();

        $this->pagination = $this->prophesize(Pagination::class);
        $this->pagination->route()->willReturn(self::ROUTE_NAME);
        $this->pagination->query()->willReturn([]);

        $this->router = $this->prophesize(RouterInterface::class);
        $this->twig = $this->prophesize(Twig_Environment::class);
        $this->extension = new PaginationExtension($this->router->reveal());
    }

    public function testGetFunctions()
    {
        $this->assertContainsOnlyInstancesOf(\Twig_SimpleFunction::class, $this->extension->getFunctions());
    }

    public function testSorterLinkWithEmptyQueryParams()
    {
        $this->generateShouldBeCalled(['sorters' => ['foo' => 'asc'], 'page' => 1]);
        $this->renderShouldBeCalled('sorters/link', [
            'key' => 'foo',
            'title' => 'Bar',
            'uri' => self::GENERATED_URL,
            'direction' => 'asc',
            'className' => '',
        ]);

        $this->extension->sorterLink($this->twig->reveal(), $this->pagination->reveal(), 'foo', 'Bar');
    }

    public function testSorterLinkWithKeyOnQueryParams()
    {
        $this->pagination->query()->willReturn(['sorters' => ['foo' => 'Asc']]);
        $this->generateShouldBeCalled(['sorters' => ['foo' => 'desc'], 'page' => 1]);
        $this->renderShouldBeCalled('sorters/link', [
            'key' => 'foo',
            'title' => 'Bar',
            'uri' => self::GENERATED_URL,
            'direction' => 'desc',
            'className' => 'desc',
        ]);

        $this->extension->sorterLink($this->twig->reveal(), $this->pagination->reveal(), 'foo', 'Bar');
    }

    public function testSorterLinkWithInvalidKeyOnQueryParams()
    {
        $this->pagination->query()->willReturn(['sorters' => ['foo' => 'Invalid']]);
        $this->generateShouldBeCalled(['sorters' => ['foo' => 'asc'], 'page' => 1]);
        $this->renderShouldBeCalled('sorters/link', [
            'key' => 'foo',
            'title' => 'Bar',
            'uri' => self::GENERATED_URL,
            'direction' => 'asc',
            'className' => 'asc',
        ]);

        $this->extension->sorterLink($this->twig->reveal(), $this->pagination->reveal(), 'foo', 'Bar');
    }

    public function testSorterLinkReplacesKeysOnQueryParams()
    {
        $this->pagination->query()->willReturn(['sorters' => ['foo' => 'desc'], 'page' => 3]);
        $this->generateShouldBeCalled(['sorters' => ['foo' => 'asc'], 'page' => 1]);
        $this->renderShouldBeCalled('sorters/link', [
            'key' => 'foo',
            'title' => 'Bar',
            'uri' => self::GENERATED_URL,
            'direction' => 'asc',
            'className' => 'asc',
        ]);

        $this->extension->sorterLink($this->twig->reveal(), $this->pagination->reveal(), 'foo', 'Bar');
    }

    public function testFilterSelect()
    {
        $options = ['foo' => 'Filter Title'];
        $this->renderShouldBeCalled('filters/select', ['key' => 'foo', 'options' => $options]);
        $this->extension->filterSelect($this->twig->reveal(), $this->pagination->reveal(), 'foo', $options);
    }

    public function testFilterDropdown()
    {
        $options = ['foo' => 'Bar'];
        $this->renderShouldBeCalled('filters/dropdown', ['key' => 'foo', 'options' => $options, 'default' => 'Bar']);
        $this->extension->filterDropdown($this->twig->reveal(), $this->pagination->reveal(), 'foo', $options);
    }

    public function testFilterSearchWithoutValue()
    {
        $this->renderShouldBeCalled('filters/search', ['key' => 'foo', 'value' => '', 'placeholder' => '']);
        $this->extension->filterSearch($this->twig->reveal(), $this->pagination->reveal(), 'foo');
    }

    public function testFilterSearchWithValue()
    {
        $this->pagination->query()->willReturn(['filters' => ['foo' => 'bar']]);
        $this->renderShouldBeCalled('filters/search', ['key' => 'foo', 'value' => 'bar', 'placeholder' => '']);
        $this->extension->filterSearch($this->twig->reveal(), $this->pagination->reveal(), 'foo');
    }

    public function testFilterSearchWithPlaceholder()
    {
        $this->renderShouldBeCalled('filters/search', ['key' => 'foo', 'value' => '', 'placeholder' => 'Bar']);
        $this->extension->filterSearch($this->twig->reveal(), $this->pagination->reveal(), 'foo', 'Bar');
    }

    public function testFilterUri()
    {
        $this->pagination->query()->willReturn(['filters' => ['foo' => 'old', 'bar' => 'baz'], 'page' => 3]);
        $this->generateShouldBeCalled(['filters' => ['foo' => 'new', 'bar' => 'baz' ], 'page' => 1]);

        $url = $this->extension->filterUri($this->pagination->reveal(), 'foo', 'new');
        $this->assertEquals(self::GENERATED_URL, $url);
    }

    public function testFilterIsActiveWhenKeyIsNotSet()
    {
        $this->assertFalse($this->extension->filterIsActive($this->pagination->reveal(), 'foo', 'bar'));
    }

    public function testFilterIsActiveWithDifferentValue()
    {
        $this->pagination->query()->willReturn(['filters' => ['foo' => 'bar']]);
        $this->assertFalse($this->extension->filterIsActive($this->pagination->reveal(), 'foo', 'baz'));
    }

    public function testFilterIsActive()
    {
        $this->pagination->query()->willReturn(['filters' => ['foo' => 'bar']]);
        $this->assertTrue($this->extension->filterIsActive($this->pagination->reveal(), 'foo', 'bar'));
    }

    public function testPagination()
    {
        $this->renderShouldBeCalled('pagination');
        $this->extension->pagination($this->twig->reveal(), $this->pagination->reveal());
    }

    public function testGetName()
    {
        $this->assertEquals('datadog_pagination_extension', $this->extension->getName());
    }

    private function generateShouldBeCalled(array $parameters)
    {
        $this->router->generate(self::ROUTE_NAME, $parameters)->shouldBeCalled()->willReturn(self::GENERATED_URL);
    }

    private function renderShouldBeCalled($template, array $parameters = [])
    {
        $parameters = array_merge(['pagination' => $this->pagination->reveal()], $parameters);
        $this->twig->render("@DataDogPager/$template.html.twig", $parameters)->shouldBeCalled();
    }
}
