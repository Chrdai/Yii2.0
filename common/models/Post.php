<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "post".
 *
 * @property integer $id
 * @property string $title
 * @property string $content
 * @property string $tags
 * @property integer $status
 * @property integer $create_time
 * @property integer $update_time
 * @property integer $author_id
 *
 * @property Comment[] $comments
 * @property Adminuser $author
 * @property Poststatus $status0
 */
class Post extends \yii\db\ActiveRecord
{
    private $_oldTags;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'post';    // 文章表
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['title', 'content', 'status', 'author_id'], 'required'],
            [['content', 'tags'], 'string'],
            [['status', 'create_time', 'update_time', 'author_id'], 'integer'],
            [['title'], 'string', 'max' => 128],
            [['author_id'], 'exist', 'skipOnError' => true, 'targetClass' => Adminuser::className(), 'targetAttribute' => ['author_id' => 'id']],
            [['status'], 'exist', 'skipOnError' => true, 'targetClass' => Poststatus::className(), 'targetAttribute' => ['status' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => '标题',
            'content' => '内容',
            'tags' => '标签',
            'status' => '状态',
            'create_time' => '创建时间',
            'update_time' => '修改时间',
            'author_id' => '作者',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     * Post 类多了一个 commets 属性，这个属性是一个数组，数组是由评论对象组成的
     * 获取评论使用：$thePost -> comments 来获取，但它是一个对象数组，如果要访问具体的某个对象，就需要用 foreach 来遍历
     */
    public function getComments()
    {
        return $this->hasMany(Comment::className(), ['post_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAuthor()
    {
        return $this->hasOne(Adminuser::className(), ['id' => 'author_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     * 建立文章类和文章状态类之间的联系，建立起这种联系后，相当于原来的 Post 类就多了一个属性 status0,这个属性值是文章状态类的对象
     * 获取状态名可以使用：$thePost->status0->name 来获取
     */
    public function getStatus0()
    {
        return $this->hasOne(Poststatus::className(), ['id' => 'status']);    // Poststatus::className 是文章状态表的表名，['id' => 'status']是关联的条件
    }

    public function beforeSave($insert){
        if(parent::beforeSave($insert)){
            if($insert){
                $this->create_time = time();
                $this->update_time = time();
            }else{
                $this->update_time = time();
            }
            return true;
        }else{
            return false;
        }
    }

    public function afterFind()
    {
        parent::afterFind(); // TODO: Change the autogenerated stub
        $this->_oldTags = $this->tags;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes); // TODO: Change the autogenerated stub
        Tag::updateFrequency($this->_oldTags,$this->tags);
    }

    public function  afterDelete()
    {
        parent::afterDelete(); // TODO: Change the autogenerated stub
        Tag::updateFrequency($this->tags,'');
    }
}

/*
 * hasOne() 和 hasMany() 的使用原则：
 *
 * 1、hasOne() 用于多对一，一对一的情况，hasMany() 用于一对多的情况
 *
 * 2、Gii 会根据数据库表之间的关联关系，自动生成建立类之间关联关系的代码
 *
 * 3、status 后面有个 0 ，是避免属性名冲突
 *
 * */

