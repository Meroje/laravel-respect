<?php

namespace Respect\Relational\Styles;

class StandardTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Respect\Relational\Styles\Standard
     */
    private $style;


    public function tableEntityProvider()
    {
        return array(
            array('post',           'Post'),
            array('comment',        'Comment'),
            array('category',       'Category'),
            array('post_category',  'PostCategory'),
            array('post_tag',       'PostTag'),
        );
    }

    public function manyToMantTableProvider()
    {
        return array(
            array('post',   'category', 'post_category'),
            array('user',   'group',    'user_group'),
            array('group',  'profile',  'group_profile'),
        );
    }

    public function columnsPropertyProvider()
    {
        return array(
            array('id'),
            array('text'),
            array('name'),
            array('content'),
            array('created'),
        );
    }
    
    public function foreignProvider()
    {
        return array(
            array('post',       'post_id'),
            array('author',     'author_id'),
            array('tag',        'tag_id'),
            array('user',       'user_id'),
        );
    }


    public function setUp()
    {
        $this->style = new Standard();
    }

    public function tearDown()
    {
        $this->style = null;
    }

    /**
     * @dataProvider tableEntityProvider
     */
    public function test_table_and_entities_methods($table, $entity)
    {
        $this->assertEquals($entity, $this->style->tableToEntity($table));
        $this->assertEquals($table, $this->style->entityToTable($entity));
        $this->assertEquals('id', $this->style->primaryFromTable($table));
    }

    /**
     * @dataProvider columnsPropertyProvider
     */
    public function test_columns_and_properties_methods($name)
    {
        $this->assertEquals($name, $this->style->columnToProperty($name));
        $this->assertEquals($name, $this->style->propertyToColumn($name));
        $this->assertFalse($this->style->isForeignColumn($name));
        $this->assertNull($this->style->tableFromForeignColumn($name));
    }

    /**
     * @dataProvider manyToMantTableProvider
     */
    public function test_table_from_left_right_table($left, $right, $table)
    {
        $this->assertEquals($table, $this->style->manyFromLeftRight($left, $right));
    }
    
    /**
     * @dataProvider foreignProvider
     */
    public function test_foreign($table, $foreign)
    {
        $this->assertTrue($this->style->isForeignColumn($foreign));
        $this->assertEquals($table, $this->style->tableFromForeignColumn($foreign));
        $this->assertEquals($foreign, $this->style->foreignFromTable($table));
    }

}

