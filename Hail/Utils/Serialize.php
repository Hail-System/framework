<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2016/2/14 0014
 * Time: 12:33
 */

namespace Hail\Utils;

/**
 * С����
 * ѹ���ߴ�:     msgpack < igbinary < json < swoole < serialize
 * ���л��ٶ�:   swoole << serialize < msgpack < json < igbinary
 * �����л��ٶ�: swoole << igbinary < msgpack < serialize << json
 *
 * ������
 * ѹ���ߴ�:     igbinary << msgpack < json << swoole < serialize
 * ���л��ٶ�:   swoole << msgpack < serialize < igbinary �� json
 * �����л��ٶ�: swoole << igbinary < serialize < msgpack << json
 *
 * serialize �� json ����Ҫ��װ�������չ
 */
defined('HAIL_SERIALIZE') || define('HAIL_SERIALIZE', 'serialize');

class Serialize
{
	private static $mode;
	private static $encode;
	private static $decode;

	public static function init()
	{
		$type = HAIL_SERIALIZE;

		switch ($type) {
			case 'msgpack':
				if (extension_loaded('msgpack')) {
					self::$mode = 'msgpack';
					self::$encode = 'msgpack_pack';
					self::$decode = 'msgpack_unpack';
				}
				break;

			case 'swoole':
				if (extension_loaded('swoole_serialize')) {
					self::$mode = 'swoole_serialize';
					self::$encode = 'swoole_serialize';
					self::$decode = 'swoole_unserialize';
				}
				break;

			case 'igbinary':
				if (extension_loaded('igbinary')) {
					self::$mode = 'igbinary';
					self::$encode = 'igbinary_serialize';
					self::$decode = 'igbinary_unserialize';
				}
				break;

			case 'json':
				self::$mode = 'json';
				self::$encode = 'Hail\Utils\Json::encode';
				self::$decode = 'Hail\Utils\Json::decode';
				break;

			case 'serialise':
			default:
				self::$mode = 'serialize';
				self::$encode = 'serialize';
				self::$decode = 'unserialize';
		}
	}

	public static function encode($value)
	{
		return (self::$encode)($value);
	}

	public static function decode($value)
	{
		$return = @(self::$decode)($value);

		return $return ?: false;
	}

	public static function encodeToString($value)
	{
		if (in_array(self::$mode, ['serialize', 'json'], true)) {
			return self::encode($value);
		}

		return base64_encode(
			self::encode($value)
		);
	}

	public static function decodeFromBase64($value)
	{
		if (in_array(self::$mode, ['serialize', 'json'], true)) {
			return self::decode($value);
		}

		return self::decode(
			base64_decode($value)
		);
	}

	public static function encodeArray($array)
	{
		return array_map(self::$encode, $array);
	}

	public static function decodeArray($array)
	{
		return array_map([self::class, 'decode'], $array);
	}

	public static function decodeArrayFromBase64($array)
	{
		return array_map([self::class, 'decodeFromBase64'], $array);
	}
}

Serialize::init();