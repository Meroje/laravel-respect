<?php

namespace Respect\Relational;

use PDO;

class MapperTest extends \PHPUnit_Framework_TestCase {

    protected $mapper, $posts, $authors, $comments, $categories, $postsCategories;

    public function setUp() {
        $conn = new PDO('sqlite::memory:');
        $db = new Db($conn);
        $conn->exec((string) Sql::createTable('post', array(
                    'id INTEGER PRIMARY KEY',
                    'title VARCHAR(255)',
                    'text TEXT',
                    'author_id INTEGER'
                )));
        $conn->exec((string) Sql::createTable('author', array(
                    'id INTEGER PRIMARY KEY',
                    'name VARCHAR(255)'
                )));
        $conn->exec((string) Sql::createTable('comment', array(
                    'id INTEGER PRIMARY KEY',
                    'post_id INTEGER',
                    'text TEXT',
                )));
        $conn->exec((string) Sql::createTable('category', array(
                    'id INTEGER PRIMARY KEY',
                    'name VARCHAR(255)',
                    'category_id INTEGER'
                )));
        $conn->exec((string) Sql::createTable('post_category', array(
                    'id INTEGER PRIMARY KEY',
                    'post_id INTEGER',
                    'category_id INTEGER'
                )));
        $this->posts = array(
            (object) array(
                'id' => 5,
                'title' => 'Post Title',
                'text' => 'Post Text',
                'author_id' => 1
            )
        );
        $this->authors = array(
            (object) array(
                'id' => 1,
                'name' => 'Author 1'
            )
        );
        $this->comments = array(
            (object) array(
                'id' => 7,
                'post_id' => 5,
                'text' => 'Comment Text'
            ),
            (object) array(
                'id' => 8,
                'post_id' => 4,
                'text' => 'Comment Text 2'
            )
        );
        $this->categories = array(
            (object) array(
                'id' => 2,
                'name' => 'Sample Category',
                'category_id' => null
            ),
            (object) array(
                'id' => 3,
                'name' => 'NONON',
                'category_id' => null
            )
        );
        $this->postsCategories = array(
            (object) array(
                'id' => 66,
                'post_id' => 5,
                'category_id' => 2
            )
        );

        foreach ($this->authors as $author)
            $db->insertInto('author', (array) $author)->values((array) $author)->exec();

        foreach ($this->posts as $post)
            $db->insertInto('post', (array) $post)->values((array) $post)->exec();

        foreach ($this->comments as $comment)
            $db->insertInto('comment', (array) $comment)->values((array) $comment)->exec();

        foreach ($this->categories as $category)
            $db->insertInto('category', (array) $category)->values((array) $category)->exec();

        foreach ($this->postsCategories as $postCategory)
            $db->insertInto('post_category', (array) $postCategory)->values((array) $postCategory)->exec();

        $mapper = new Mapper($conn);
        $this->mapper = $mapper;
        $this->conn = $conn;
    }

    public function test_creating_with_db_instance()
    {
        $db = new Db($this->conn);
        $mapper = new Mapper($db);
        $this->assertAttributeSame($db, 'db', $mapper);
    }

    public function test_creating_with_invalid_args_should_throw_exception()
    {
        $this->setExpectedException('InvalidArgumentException');
        $mapper = new Mapper('foo');
    }

    public function test_rolling_back_transaction()
    {
        $conn = $this->getMock(
            'PDO',
            array('beginTransaction', 'rollback', 'prepare', 'execute'),
            array('sqlite::memory:')
        );
        $conn->expects($this->any())
             ->method('prepare')
             ->will($this->throwException(new \Exception));
        $conn->expects($this->once())
             ->method('rollback');
        $mapper = new Mapper($conn);
        $obj = new \stdClass();
        $obj->id = null;
        $mapper->foo->persist($obj);
        try {
            $mapper->flush();
        } catch (\Exception $e) {
            //OK!
        }
    }

    public function test_ignoring_last_insert_id_errors()
    {
        $conn = $this->getMock(
            'PDO',
            array('lastInsertId'),
            array('sqlite::memory:')
        );
        $conn->exec('CREATE TABLE foo(id INTEGER PRIMARY KEY)');
        $conn->expects($this->any())
             ->method('lastInsertId')
             ->will($this->throwException(new \PDOException));
        $mapper = new Mapper($conn);
        $obj = new \stdClass();
        $obj->id = null;
        $mapper->foo->persist($obj);
        $mapper->flush();
        //Ok, should not throw PDOException on this.
    }

    public function test_removing_untracked_object()
    {
        $comment = new \stdClass();
        $comment->id = 7;
        $this->assertNotEmpty($this->mapper->comment[7]->fetch());
        $this->mapper->comment->remove($comment);
        $this->mapper->flush();
        $this->assertEmpty($this->mapper->comment[7]->fetch());
    }

    public function test_fetching_single_entity_from_collection_should_return_first_record_from_table()
    {
        $expectedFirstComment = reset($this->comments);
        $fetchedFirstComment = $this->mapper->comment->fetch();
        $this->assertEquals($expectedFirstComment, $fetchedFirstComment);
    }

    public function test_fetching_all_entites_from_collection_should_return_all_records()
    {
        $expectedCategories = $this->categories;
        $fetchedCategories = $this->mapper->category->fetchAll();
        $this->assertEquals($expectedCategories, $fetchedCategories);
    }

    public function test_extra_sql_on_single_fetch_should_be_applied_on_mapper_sql()
    {
        $expectedLast = end($this->comments);
        $fetchedLast = $this->mapper->comment->fetch(Sql::orderBy('id DESC'));
        $this->assertEquals($expectedLast, $fetchedLast);
    }
    public function test_extra_sql_on_fetchAll_should_be_applied_on_mapper_sql()
    {
        $expectedComments = array_reverse($this->comments);
        $fetchedComments = $this->mapper->comment->fetchAll(Sql::orderBy('id DESC'));
        $this->assertEquals($expectedComments, $fetchedComments);
    }

    public function test_nested_collections_should_hydrate_results() {
        $mapper = $this->mapper;
        $comment = $mapper->comment->post[5]->fetch();
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(3, count(get_object_vars($comment)));
        $this->assertEquals(5, $comment->post_id->id);
        $this->assertEquals('Post Title', $comment->post_id->title);
        $this->assertEquals('Post Text', $comment->post_id->text);
        $this->assertEquals(4, count(get_object_vars($comment->post_id)));
    }

    public function testOneToN() {
        $mapper = $this->mapper;
        $comments = $mapper->comment->post($mapper->author)->fetchAll();
        $comment = current($comments);
        $this->assertEquals(1, count($comments));
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(3, count(get_object_vars($comment)));
        $this->assertEquals(5, $comment->post_id->id);
        $this->assertEquals('Post Title', $comment->post_id->title);
        $this->assertEquals('Post Text', $comment->post_id->text);
        $this->assertEquals(4, count(get_object_vars($comment->post_id)));
        $this->assertEquals(1, $comment->post_id->author_id->id);
        $this->assertEquals('Author 1', $comment->post_id->author_id->name);
        $this->assertEquals(2, count(get_object_vars($comment->post_id->author_id)));
    }

    public function testNtoN() {
        $mapper = $this->mapper;
        $comments = $mapper->comment->post->post_category->category[2]->fetchAll();
        $comment = current($comments);
        $this->assertEquals(1, count($comments));
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(3, count(get_object_vars($comment)));
        $this->assertEquals(5, $comment->post_id->id);
        $this->assertEquals('Post Title', $comment->post_id->title);
        $this->assertEquals('Post Text', $comment->post_id->text);
        $this->assertEquals(4, count(get_object_vars($comment->post_id)));
    }

    public function testNtoNReverse() {
        $mapper = $this->mapper;
        $cat = $mapper->category->post_category->post[5]->fetch();
        $this->assertEquals(2, $cat->id);
        $this->assertEquals('Sample Category', $cat->name);
    }

    public function testTracking() {
        $mapper = $this->mapper;
        $c7 = $mapper->comment[7]->fetch();
        $c8 = $mapper->comment[8]->fetch();
        $p5 = $mapper->post[5]->fetch();
        $c3 = $mapper->category[2]->fetch();
        $this->assertTrue($mapper->isTracked($c7));
        $this->assertTrue($mapper->isTracked($c8));
        $this->assertTrue($mapper->isTracked($p5));
        $this->assertTrue($mapper->isTracked($c3));
        $this->assertSame($c7, $mapper->getTracked('comment', 7));
        $this->assertSame($c8, $mapper->getTracked('comment', 8));
        $this->assertSame($p5, $mapper->getTracked('post', 5));
        $this->assertSame($c3, $mapper->getTracked('category', 2));
        $this->assertFalse($mapper->getTracked('none', 3));
        $this->assertFalse($mapper->getTracked('comment', 9889));
    }

    public function testSimplePersist() {
        $mapper = $this->mapper;
        $entity = (object) array('id' => 4, 'name' => 'inserted', 'category_id' => null);
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->conn->query('select * from category where id=4')->fetch(PDO::FETCH_OBJ);
        $this->assertEquals($entity, $result);
    }
    public function testSimplePersistCollection() {
        $mapper = $this->mapper;
        $entity = (object) array('id' => 4, 'name' => 'inserted', 'category_id' => null);
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->conn->query('select * from category where id=4')->fetch(PDO::FETCH_OBJ);
        $this->assertEquals($entity, $result);
    }

    public function testNestedPersistCollection() {
        $postWithAuthor = (object) array(
            'id' => null,
            'title' => 'hi',
            'text' => 'hi text',
            'author_id' => (object) array(
                'id' => null,
                'name' => 'New'
            )
        );
        $this->mapper->post->author->persist($postWithAuthor);
        $this->mapper->flush();
        $author = $this->conn->query('select * from author order by id desc limit 1')->fetch(PDO::FETCH_OBJ);
        $post = $this->conn->query('select * from post order by id desc limit 1')->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('New', $author->name);
        $this->assertEquals('hi', $post->title);
    }
    public function testNestedPersistCollectionShortcut() {
        $postWithAuthor = (object) array(
            'id' => null,
            'title' => 'hi',
            'text' => 'hi text',
            'author_id' => (object) array(
                'id' => null,
                'name' => 'New'
            )
        );
        $this->mapper->postAuthor = $this->mapper->post->author;
        $this->mapper->postAuthor->persist($postWithAuthor);
        $this->mapper->flush();
        $author = $this->conn->query('select * from author order by id desc limit 1')->fetch(PDO::FETCH_OBJ);
        $post = $this->conn->query('select * from post order by id desc limit 1')->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('New', $author->name);
        $this->assertEquals('hi', $post->title);
    }

    public function testNestedPersistCollectionWithChildrenShortcut() {
        $postWithAuthor = (object) array(
            'id' => null,
            'title' => 'hi',
            'text' => 'hi text',
            'author_id' => (object) array(
                'id' => null,
                'name' => 'New'
            )
        );
        $this->mapper->postAuthor = $this->mapper->post($this->mapper->author);
        $this->mapper->postAuthor->persist($postWithAuthor);
        $this->mapper->flush();
        $author = $this->conn->query('select * from author order by id desc limit 1')->fetch(PDO::FETCH_OBJ);
        $post = $this->conn->query('select * from post order by id desc limit 1')->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('New', $author->name);
        $this->assertEquals('hi', $post->title);
    }

    public function testSubCategory() {
        $mapper = $this->mapper;
        $entity = (object) array('id' => 8, 'name' => 'inserted', 'category_id' => 2);
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->conn->query('select * from category where id=8')->fetch(PDO::FETCH_OBJ);
        $result2 = $mapper->category[8]->category->fetch();
        $this->assertEquals($result->id, $result2->id);
        $this->assertEquals($result->name, $result2->name);
        $this->assertEquals($entity, $result);
    }
    public function testSubCategoryCondition() {
        $mapper = $this->mapper;
        $entity = (object) array('id' => 8, 'name' => 'inserted', 'category_id' => 2);
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->conn->query('select * from category where id=8')->fetch(PDO::FETCH_OBJ);
        $result2 = $mapper->category(array("id"=>8))->category->fetch();
        $this->assertEquals($result->id, $result2->id);
        $this->assertEquals($result->name, $result2->name);
        $this->assertEquals($entity, $result);
    }

    public function testAutoIncrementPersist() {
        $mapper = $this->mapper;
        $entity = (object) array('id' => null, 'name' => 'inserted', 'category_id' => null);
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->conn->query('select * from category where name="inserted"')->fetch(PDO::FETCH_OBJ);
        $this->assertEquals($entity, $result);
        $this->assertEquals(4, $result->id);
    }

    public function testPassedIdentity() {
        $mapper = $this->mapper;

        $post = new \stdClass;
        $post->id = null;
        $post->title = 12345;
        $post->text = 'text abc';

        $comment = new \stdClass;
        $comment->id = null;
        $comment->post_id = $post;
        $comment->text = 'abc';

        $mapper->post->persist($post);
        $mapper->comment->persist($comment);
        $mapper->flush();

        $postId = $this->conn
                ->query('select id from post where title = 12345')
                ->fetchColumn(0);

        $comment = $this->conn->query('select * from comment where post_id = ' . $postId)
                ->fetchObject();

        $this->assertEquals('abc', $comment->text);
    }

    public function testJoinedPersist() {
        $mapper = $this->mapper;
        $entity = $mapper->comment[8]->fetch();
        $entity->text = 'HeyHey';
        $mapper->comment->persist($entity);
        $mapper->flush();
        $result = $this->conn->query('select text from comment where id=8')->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }


    public function testRemove() {
        $mapper = $this->mapper;
        $c8 = $mapper->comment[8]->fetch();
        $pre = $this->conn->query('select count(*) from comment')->fetchColumn(0);
        $mapper->comment->remove($c8);
        $mapper->flush();
        $total = $this->conn->query('select count(*) from comment')->fetchColumn(0);
        $this->assertEquals($total, $pre - 1);
    }

    public function test_fetching_entity_typed()
    {
        $mapper = $this->mapper;
        $mapper->entityNamespace = '\Respect\Relational\\';
        $comment = $mapper->comment[8]->fetch();
        $this->assertInstanceOf('\Respect\Relational\Comment', $comment);
    }

    public function test_fetching_all_entity_typed()
    {
        $mapper = $this->mapper;
        $mapper->entityNamespace = '\Respect\Relational\\';
        $comment = $mapper->comment->fetchAll();
        $this->assertInstanceOf('\Respect\Relational\Comment', $comment[1]);
    }

    public function test_fetching_all_entity_typed_nested()
    {
        $mapper = $this->mapper;
        $mapper->entityNamespace = '\Respect\Relational\\';
        $comment = $mapper->comment->post->fetchAll();
        $this->assertInstanceOf('\Respect\Relational\Comment', $comment[0]);
        $this->assertInstanceOf('\Respect\Relational\Post', $comment[0]->post_id);
    }

    public function test_persisting_entity_typed()
    {
        $mapper = $this->mapper;
        $mapper->entityNamespace = '\Respect\Relational\\';
        $comment = $mapper->comment[8]->fetch();
        $comment->text = 'HeyHey';
        $mapper->comment->persist($comment);
        $mapper->flush();
        $result = $this->conn->query('select text from comment where id=8')->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function test_persisting_new_entity_typed()
    {
        $mapper = $this->mapper;
        $mapper->entityNamespace = '\Respect\Relational\\';
        $comment = new Comment();
        $comment->text = 'HeyHey';
        $mapper->comment->persist($comment);
        $mapper->flush();
        $result = $this->conn->query('select text from comment where id=9')->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function test_style()
    {
        $this->assertInstanceOf('Respect\Relational\Styles\Stylable', $this->mapper->getStyle());
        $this->assertInstanceOf('Respect\Relational\Styles\Standard', $this->mapper->getStyle());
        $styles = array(
            new Styles\CakePHP(),
            new Styles\NorthWind(),
            new Styles\Sakila(),
            new Styles\Standard(),
        );
        foreach ($styles as $style) {
            $this->mapper->setStyle($style);
            $this->assertEquals($style, $this->mapper->getStyle());
        }
    }

}

class Comment {
    public $id=null, $post_id=null, $text=null;
}

class Post {
    public $id=null, $author_id=null, $text=null;
}

