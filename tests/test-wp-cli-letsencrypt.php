<?php
/**
 * Class WpCliLetsencryptTest
 *
 * @package Wp_Cli_Letsencrypt
 */

class WpCliLetsencryptTest extends WP_UnitTestCase {

  private static $user_id;
  private static $blog_id;

  static function wpSetUpBeforeClass(){
    global $current_site;
    self::$user_id = wpmu_create_user('user', sha1('foo'), 'user@example.org' );
    self::$blog_id = array();
    self::$blog_id[] = wpmu_create_blog('example.com', '/', 'FQDN website', self::$user_id , array( 'public' => 1 ), $current_site->id );
    self::$blog_id[] = wpmu_create_blog('subdomain.example.org', '/', 'Subdomain website', self::$user_id , array( 'public' => 1 ), $current_site->id );
    self::$blog_id[] = wpmu_create_blog('example.fr', '/', 'HTTPS website', self::$user_id , array( 'public' => 1 ), $current_site->id );
    switch_to_blog(end(self::$blog_id));
    update_option( 'siteurl', 'https://example.fr' );
    restore_current_blog();
  }

  static function wpTearDownAfterClass(){
    foreach (self::$blog_id as $blog_id) wpmu_delete_blog( $blog_id, true );
    wpmu_delete_user( self::$user_id );
  }

  function test_wp_cli_letsencrypt_domains(){
    $this->assertEquals(array(
      2 => 'example.com',
      4 => 'example.fr',
    ), wp_cli_letsencrypt_domains());
    $this->assertEquals(array(
      2 => 'example.com',
      3 => 'subdomain.example.org',
      4 => 'example.fr',
    ), wp_cli_letsencrypt_domains(array('subdomains' => true)));
    $this->assertEquals(array(
      'example.com',
      'example.fr',
      'www.example.com',
      'www.example.fr',
    ), array_values(wp_cli_letsencrypt_domains(array('with-www' => true))));
    $this->assertEquals(array(
      4 => 'example.fr',
    ), wp_cli_letsencrypt_domains(array('https-only' => true)));
  }

  function test_wp_cli_letsencrypt_domains_with_www() {
    $domains = array('foo.com', 'www.bar.com');
    $this->assertEquals(array(
      'www.foo.com',
      'bar.com',
    ), wp_cli_letsencrypt_domains_with_www($domains));
  }
  
  function test_wp_cli_letsencrypt_domains_https_only() {
    $domains = array(4 => 'example.fr');
    $this->assertEquals(array(
      4 => 'example.fr',
    ), wp_cli_letsencrypt_domains_https_only($domains));
  }
}
