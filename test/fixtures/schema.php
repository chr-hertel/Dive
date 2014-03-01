<?php

return array(
    'tables' => array(
        'author' => array(
            'fields' => array(
                'id'    => array(
                    'primary'   => true,
                    'type'      => 'integer',
                    'length'    => 10,
                    'unsigned'  => true,
                    'autoIncrement' => true
                ),
                'firstname'  => array(
                    'type'      => 'string',
                    'length'    => 64,
                    'nullable'  => true
                ),
                'lastname'  => array(
                    'type'      => 'string',
                    'length'    => 64
                ),
                'email' => array(
                    'type'      => 'string',
                    'length'    => 255
                ),
                'user_id' => array(
                    'type'      => 'integer',
                    'length'    => 10,
                    'unsigned'  => true,
                    'foreign'   => 'user.id'
                ),
                'editor_id' => array(
                    'type'      => 'integer',
                    'length'    => 10,
                    'unsigned'  => true,
                    'nullable'  => true,
                    'foreign'   => 'author.id'
                ),
            ),
            'indexes' => array(
                'UNIQUE' => array(
                    'type' => 'unique',
                    'fields' => array(
                        'firstname',
                        'lastname'
                    )
                ),
                'UQ_user_id' => array(
                    'type' => 'unique',
                    'fields' => array(
                        'user_id'
                    )
                )
            ),
            'recordClass' => '\Dive\TestSuite\Model\Author'
        ),
        'user' => array(
            'fields' => array(
                'id'    => array(
                    'primary'   => true,
                    'type'      => 'integer',
                    'length'    => 10,
                    'unsigned'  => true,
                    'autoIncrement' => true
                ),
                'username'  => array(
                    'type'      => 'string',
                    'length'    => 64
                ),
                'password'  => array(
                    'type'      => 'string',
                    'length'    => 32
                )
            ),
            'indexes' => array(
                'UNIQUE' => array(
                    'type' => 'unique',
                    'fields' => array(
                        'username'
                    )
                )
            ),
            'recordClass' => '\Dive\TestSuite\Model\User'
        ),
        'donation' => array(
            'fields' => array(
                'id' => array(
                    'primary' => true,
                    'type' => 'integer',
                    'length' => 10,
                    'unsigned' => true,
                    'autoIncrement' => true
                ),
                'article_id' => array(
                    'type' => 'integer',
                    'length' => 10,
                    'unsigned' => true,
                    'nullable' => true,
                ),
                'author_id' => array(
                    'type' => 'integer',
                    'length' => 10,
                    'unsigned' => true,
                    'nullable' => true,
                ),
                'comment_id' => array(
                    'type' => 'integer',
                    'length' => 10,
                    'unsigned' => true,
                    'nullable' => true,
                ),
                'is_cancelled' => array(
                    'type' => 'integer',
                    'length' => 1,
                    'unsigned' => true,
                    'default' => '0'
                ),
                'value' => array(
                    'type' => 'decimal',
                    'precision' => 2,
                    'length' => 14,
                    'nullable' => true
                )
            ),
            'recordClass' => '\Dive\TestSuite\Model\Donation'
        ),
        'article' => array(
            'fields' => array(
                'id'    => array(
                    'primary'   => true,
                    'type'      => 'integer',
                    'length'    => 10,
                    'unsigned'  => true,
                    'autoIncrement' => true
                ),
                'author_id' => array(
                    'type'      => 'integer',
                    'length'    => 10,
                    'unsigned'  => true,
                    'foreign'   => 'author.id'
                ),
                'is_published' => array(
                    'type'      => 'integer',
                    'length'    => 1,
                    'unsigned'  => true,
                    'default'   => '0'
                ),
                'title' => array(
                    'type'      => 'string',
                    'length'    => 64
                ),
                'teaser' => array(
                    'type'      => 'string',
                    'length'    => 255
                ),
                'text' => array(
                    'type'      => 'string',
                    'length'    => 2000
                ),
                'created_on' => array(
                    'type'      => 'datetime',
                    'nullable'  => true
                ),
                'saved_on' => array(
                    'type'      => 'datetime',
                    'nullable'  => true
                ),
                'changed_on' => array(
                    'type'      => 'datetime',
                    'nullable'  => true
                )
            ),
            'behaviours' => array(
                array(
                    'class' => '\Dive\Behaviour\Timestampable',
                    'instanceShared' => true,
                    'args' => array(
                        'onInsert' => array('created_on'),
                        'onUpdate' => array('changed_on'),
                        'onSave' => array('saved_on')
                    )
                )
            ),
            'recordClass' => '\Dive\TestSuite\Model\Article'
        ),
        'comment' => array(
            'fields' => array(
                'id'    => array(
                    'primary'   => true,
                    'type'      => 'integer',
                    'length'    => 10,
                    'unsigned'  => true,
                    'autoIncrement' => true
                ),
                'user_id' => array(
                    'type'      => 'integer',
                    'length'    => 10,
                    'unsigned'  => true,
                    'foreign'   => 'user.id'
                ),
                'article_id' => array(
                    'type'      => 'integer',
                    'length'    => 10,
                    'unsigned'  => true,
                    'foreign'   => 'article.id'
                ),
                'title' => array(
                    'type'      => 'string',
                    'length'    => 64
                ),
                'text' => array(
                    'type'      => 'string',
                    'length'    => 2000
                ),
                'datetime' => array(
                    'type'      => 'datetime'
                )
            ),
            'recordClass' => '\Dive\TestSuite\Model\Comment'
        ),
        'tag' => array(
            'fields' => array(
                'id'    => array(
                    'primary'   => true,
                    'type'      => 'integer',
                    'length'    => 10,
                    'unsigned'  => true,
                    'autoIncrement' => true
                ),
                'name' => array(
                    'type'      => 'string',
                    'length'    => 64
                )
            ),
            'indexes' => array(
                'UNIQUE' => array(
                    'type' => 'unique',
                    'fields' => array(
                        'name'
                    )
                )
            ),
            'recordClass' => '\Dive\TestSuite\Model\Tag'
        ),
        'article2tag' => array(
            'fields' => array(
                'article_id' => array(
                    'primary'   => true,
                    'type'      => 'integer',
                    'length'    => 10,
                    'unsigned'  => true,
                    'foreign'   => 'article.id'
                ),
                'tag_id' => array(
                    'primary'   => true,
                    'type'      => 'integer',
                    'length'    => 10,
                    'unsigned'  => true,
                    'foreign'   => 'tag.id'
                )
            ),
            'recordClass' => '\Dive\TestSuite\Model\Article2tag'
        )
    ),
    'relations' => array(
        'article.author_id' => array(
            'owningAlias' => 'Article',
            'owningField' => 'author_id',
            'owningTable' => 'article',
            'refAlias' => 'Author',
            'refField' => 'id',
            'refTable' => 'author',
            'type' => '1-m',
            'onUpdate' => 'CASCADE',
            'onDelete' => 'RESTRICT'
        ),
        'author.user_id' => array(
            'owningAlias' => 'Author',
            'owningField' => 'user_id',
            'owningTable' => 'author',
            'refAlias' => 'User',
            'refField' => 'id',
            'refTable' => 'user',
            'type' => '1-1',
            'onUpdate' => 'CASCADE',
            'onDelete' => 'CASCADE'
        ),
        'author.editor_id' => array(
            'owningAlias' => 'Author',
            'owningField' => 'editor_id',
            'owningTable' => 'author',
            'refAlias' => 'Editor',
            'refField' => 'id',
            'refTable' => 'author',
            'type' => '1-m',
            'onUpdate' => 'CASCADE',
            'onDelete' => 'SET NULL'
        ),
        'comment.user_id' => array(
            'owningAlias' => 'Comment',
            'owningField' => 'user_id',
            'owningTable' => 'comment',
            'refAlias' => 'User',
            'refField' => 'id',
            'refTable' => 'user',
            'type' => '1-m',
            'onUpdate' => 'CASCADE',
            'onDelete' => 'CASCADE'
        ),
        'comment.article_id' => array(
            'owningAlias' => 'Comment',
            'owningField' => 'article_id',
            'owningTable' => 'comment',
            'refAlias' => 'Article',
            'refField' => 'id',
            'refTable' => 'article',
            'type' => '1-m',
            'onUpdate' => 'CASCADE',
            'onDelete' => 'CASCADE'
        ),
        'article2tag.article_id' => array(
            'owningAlias' => 'Article2tagHasMany',
            'owningField' => 'article_id',
            'owningTable' => 'article2tag',
            'refAlias' => 'Article',
            'refField' => 'id',
            'refTable' => 'article',
            'type' => '1-m',
            'onUpdate' => 'CASCADE',
            'onDelete' => 'CASCADE'
        ),
        'article2tag.tag_id' => array(
            'owningAlias' => 'Article2tagHasMany',
            'owningField' => 'tag_id',
            'owningTable' => 'article2tag',
            'refAlias' => 'Tag',
            'refField' => 'id',
            'refTable' => 'tag',
            'type' => '1-m',
            'onUpdate' => 'CASCADE',
            'onDelete' => 'CASCADE'
        )
    ),
    'views' => array(
        'author_user_view' => array(
            'fields' => array(
                'id' => array(
                    'type' => 'integer',
                    'nullable' => true
                ),
                'firstname' => array(
                    'type' => 'string',
                    'length' => 64,
                    'nullable' => true
                ),
                'lastname' => array(
                    'type' => 'string',
                    'length' => 64,
                    'nullable' => true
                ),
                'email' => array(
                    'type' => 'string',
                    'length' => 255,
                    'nullable' => true
                ),
                'username' => array(
                    'type' => 'string',
                    'length' => 64,
                    'nullable' => true
                ),
                'password' => array(
                    'type' => 'string',
                    'length' => 32,
                    'nullable' => true
                )
            ),
            'sqlStatement' => 'SELECT a.id, a.firstname, a.lastname, a.email, u.username, u.password FROM author a LEFT JOIN user u ON a.user_id = u.id'
        )
    )
);
