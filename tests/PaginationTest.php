<?php

namespace Test;

use DataDog\PagerBundle\Pagination;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\TestCase;

class PaginationTest extends TestCase
{
    /**
     * @var Expr|\Prophecy\Prophecy\ObjectProphecy
     */
    private $expr;

    /**
     * @var AbstractQuery|\Prophecy\Prophecy\ObjectProphecy
     */
    private $query;

    /**
     * @var QueryBuilder|\PHPUnit\Framework\MockObject\MockObject
     */
    private $qb;

    protected function setUp()
    {
        parent::setUp();

        $this->expr = $this->prophesize(Expr::class);
        $this->expr->countDistinct(Argument::any())->willReturn('');
        $this->query = $this->prophesize(AbstractQuery::class);
        $this->query->getSingleScalarResult()->willReturn(1);
        $this->query->getResult()->willReturn([]);
        $this->qb = $this->createMock(QueryBuilder::class);
        $this->qb->method('expr')->willReturn($this->expr->reveal());
        $this->qb->method('getQuery')->willReturn($this->query->reveal());
    }

    public function testUnsetSystemParameters()
    {
        $request = new Request(['foo' => 'bar'], [], ['_system' => 'baz']);
        $pagination = new Pagination($this->qb, $request);
        $this->assertArrayNotHasKey('_system', $pagination->query());
        $this->assertArraySubset(['foo' => 'bar'], $pagination->query());
    }

    public function testDefaultSortersAndFilters()
    {
        $defaults = ['sorters' => [], 'filters' => []];
        $pagination = new Pagination($this->qb, new Request());
        $this->assertArraySubset($defaults, $pagination->query());
    }

    public function testApplyCustomSorters()
    {
        $request = new Request(['sorters' => ['foo' => 'DESC']]);
        $this->qb->expects($this->once())->method('addOrderBy')->with('foo', 'DESC');
        new Pagination($this->qb, $request);
    }

    public function testApplyCustomSortersHandlerWithNoDifference()
    {
        $request = new Request(['sorters' => ['foo' => 'DESC']]);
        $this->qb->expects($this->once())->method('addOrderBy')->with('foo', 'DESC');
        $applySorter = function() {
            return null;
        };
        new Pagination($this->qb, $request, ['applySorter' => $applySorter]);
    }

    public function testApplyCustomSortersHandlerWithDifference()
    {
        $request = new Request(['sorters' => ['foo' => 'DESC']]);
        $this->qb->expects($this->never())->method('addOrderBy')->with('foo', 'DESC');
        $applySorter = function(QueryBuilder $qb, $key, $direction) {
            $this->assertEquals('foo', $key);
            $this->assertEquals('DESC', $direction);
            $qb->method('getDQL')->willReturn('');
        };
        new Pagination($this->qb, $request, ['applySorter' => $applySorter]);
    }

    public function testApplyCustomFilters()
    {
        $request = new Request(['filters' => ['foo' => 'bar']]);
        $this->qb->expects($this->once())->method('setParameter')->with('foo', 'bar');
        $this->expr->eq('foo', ':foo')->shouldBeCalled();
        new Pagination($this->qb, $request);
    }

    public function testApplyCustomFiltersWithAnyValue()
    {
        $request = new Request(['filters' => ['foo' => Pagination::$filterAny]]);
        $this->qb->expects($this->never())->method('setParameter')->with('foo', Pagination::$filterAny);
        $this->expr->eq('foo', ':foo')->shouldNotBeCalled();
        new Pagination($this->qb, $request);
    }

    public function testApplyCustomFiltersWithRangeValue()
    {
        $request = new Request(['filters' => ['foo' => ['bar', 'baz']]]);
        $this->expr->in('foo', ':foo')->shouldBeCalled();
        new Pagination($this->qb, $request);
    }

    public function testReplaceNonAlphaKeysWhileApplyingCustomFilters()
    {
        $request = new Request(['filters' => ['foo2' => 'bar']]);
        $this->qb->expects($this->once())->method('setParameter')->with('foo_', 'bar');
        $this->expr->eq('foo2', ':foo_')->shouldBeCalled();
        new Pagination($this->qb, $request);
    }

    public function testApplyCustomFiltersHandler()
    {
        $request = new Request(['filters' => ['foo' => 'bar']]);
        $applyFilter = function(QueryBuilder $qb, $key, $val) {
            $qb->expects($this->never())->method('setParameter')->with('foo', 'bar');
            $this->assertEquals('foo', $key);
            $this->assertEquals('bar', $val);
        };
        new Pagination($this->qb, $request, ['applyFilter' => $applyFilter]);
    }

    public function testTotalCount()
    {
        $this->qb->expects($this->once())->method('select');
        $this->qb->expects($this->once())->method('resetDQLPart')->with('orderBy');
        $this->query->getSingleScalarResult()->willReturn('3');
        $pagination = new Pagination($this->qb, new Request());
        $this->assertSame(3, $pagination->total());
    }

    public function testApplyCounterForTotalCount()
    {
        $this->qb->expects($this->never())->method('select');
        $this->qb->expects($this->never())->method('resetDQLPart')->with('orderBy');
        $applyCounter = function(QueryBuilder $qb) {
            $this->query->getSingleScalarResult()->willReturn(5);
            return $qb;
        };
        $pagination = new Pagination($this->qb, new Request(), ['applyCounter' => $applyCounter]);
        $this->assertEquals(5, $pagination->total());
    }

    public function testDefaultCurrentPage()
    {
        $pagination = new Pagination($this->qb, new Request());
        $this->assertEquals(1, $pagination->currentPage());
    }

    public function testCurrentPage()
    {
        $this->query->getSingleScalarResult()->willReturn(100);
        $pagination = new Pagination($this->qb, new Request(['page' => 2]));
        $this->assertEquals(2, $pagination->currentPage());
    }

    public function testFixInvalidCurrentPage()
    {
        $this->query->getSingleScalarResult()->willReturn(100);
        $pagination = new Pagination($this->qb, new Request(['page' => '-2']));
        $this->assertEquals(2, $pagination->currentPage());
    }

    public function testSetLastPageAsCurrentPage()
    {
        $pagination = new Pagination($this->qb, new Request(['page' => 3]));
        $this->assertEquals(1, $pagination->currentPage());
    }

    public function testDefaultItemsPerPage()
    {
        $pagination = new Pagination($this->qb, new Request());
        $this->assertEquals(10, $pagination->itemsPerPage());
    }

    public function testItemsPerPage()
    {
        $pagination = new Pagination($this->qb, new Request(['limit' => '-50']));
        $this->assertEquals(50, $pagination->itemsPerPage());
    }

    public function testMaxItemsPerPage()
    {
        $pagination = new Pagination($this->qb, new Request(['limit' => 1000]));
        $this->assertEquals(Pagination::$maxPerPage, $pagination->itemsPerPage());
    }

    public function testRouteName()
    {
        $request = new Request([], [], ['_route' => 'foo']);
        $pagination = new Pagination($this->qb, $request);
        $this->assertEquals('foo', $pagination->route());
    }

    public function testPaginationView()
    {
        $this->query->getSingleScalarResult()->willReturn(100);
        $pagination = new Pagination($this->qb, new Request(['page' => 2]));
        $view = $pagination->pagination();
        $this->assertArraySubset(['first' => 1], $view);
        $this->assertArraySubset(['last' => 10], $view);
        $this->assertArraySubset(['current' => 2], $view);
        $this->assertArraySubset(['previous' => 1], $view);
        $this->assertArraySubset(['next' => 3], $view);
        $this->assertArraySubset(['numItemsPerPage' => 10], $view);
        $this->assertArraySubset(['pageCount' => 10], $view);
        $this->assertArraySubset(['totalCount' => 100], $view);
        $this->assertArraySubset(['pageRange' => 10], $view);
        $this->assertArraySubset(['startPage' => 1], $view);
        $this->assertArraySubset(['endPage' => 10], $view);
        $this->assertArraySubset(['pagesInRange' => range(1, 10)], $view);
        $this->assertArraySubset(['firstPageInRange' => 1], $view);
        $this->assertArraySubset(['lastPageInRange' => 10], $view);
        $this->assertArraySubset(['currentItemCount' => 0], $view);
        $this->assertArraySubset(['firstItemNumber' => 11], $view);
        $this->assertArraySubset(['lastItemNumber' => 10], $view);
    }
}
