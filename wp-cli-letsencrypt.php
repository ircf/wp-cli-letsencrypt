<?php
/*
Plugin Name: WP CLI Let's Encrypt
Plugin URI: https://ircf.fr/
Description: A simple CLI SSL certificate generator for WordPress network.
Version: 0.2
Author: IRCF
Author URI: https://ircf.fr/
License: GPL2
*/

// Check configuration
$certbot_cmd = '';
if (exec('which certbot-auto') != '') $certbot_cmd = 'certbot-auto';
if (exec('which certbot') != '') $certbot_cmd = 'certbot';
if (empty($certbot_cmd)) throw new Exception('certbot-auto or certbot is required');
if (exec('which wp') == '') throw new Exception('wp cli is required');

// Let's encrypt cleaner helper
// Warning : this removes ALL let's encrypt config from server
// TODO fix --expand and remove this
function wp_cli_letsencrypt_clean(){
  exec('cd /etc/letsencrypt && rm -rf live/* renewal/* archive/* csr/* keys/*');
}

// Domains helpers
function wp_cli_letsencrypt_domains($opts = array()){
  if (!isset($opts['mapping'])) $opts['mapping'] = false;
  if (!isset($opts['subdomains'])) $opts['subdomains'] = false;
  if (!isset($opts['https-only'])) $opts['https-only'] = false;
  if (!isset($opts['with-www'])) $opts['with-www'] = false;
  if (!isset($opts['wildcard'])) $opts['with-wildcard'] = false;
  global $wpdb;
  $rows = $wpdb->get_results("
    SELECT DISTINCT tmp.blog_id, tmp.domain, tmp.mapping FROM (
      SELECT blog_id, domain, 0 AS mapping FROM $wpdb->blogs WHERE spam=0 AND deleted=0 AND archived=0
      ".($opts['mapping'] ? "UNION
      SELECT blog_id, domain, 1 AS mapping FROM wp_domain_mapping WHERE EXISTS(
        SELECT 1 FROM $wpdb->blogs WHERE $wpdb->blogs.spam=0 AND $wpdb->blogs.deleted=0 AND $wpdb->blogs.archived=0 AND $wpdb->blogs.blog_id=wp_domain_mapping.blog_id
      )" : "")."
    ) AS tmp
    WHERE domain<>'".DOMAIN_CURRENT_SITE."' ".($opts['subdomains'] ? "" : "AND domain NOT LIKE '%.".DOMAIN_CURRENT_SITE."'")."
    ORDER BY tmp.domain
  ", ARRAY_A);
  $domains = array();
  foreach($rows as $row){
    $domains[$row['blog_id'] . ($row['mapping'] ? '_' . $row['domain'] : '')] = $row['domain'];
  }
  if ($opts['https-only']) $domains = wp_cli_letsencrypt_domains_https_only($domains);
  if ($opts['with-www']) $domains = array_merge($domains, wp_cli_letsencrypt_domains_with_www($domains));
  if ($opts['with-wildcard']) $domains[] = '*.' . DOMAIN_CURRENT_SITE; // TODO wildcard for each domain
  return $domains;
}
function wp_cli_letsencrypt_domains_https_only($domains){
  global $wpdb;
  $https_domains = array();
  foreach($domains as $blog_id => $domain){
    if (!ctype_digit(''.$blog_id)) continue; // TODO domain mapping support
    switch_to_blog($blog_id);
    $is_https = !!$wpdb->get_var( "SELECT 1 FROM $wpdb->options WHERE option_name='siteurl' AND option_value LIKE 'https://%'" );
    restore_current_blog();
    if ($is_https) $https_domains[$blog_id] = $domain;
  }
  return $https_domains;
}
function wp_cli_letsencrypt_domains_with_www($domains){
  $www_domains = array();
  foreach($domains as $domain){
    $www_domains[] = strpos($domain, 'www.') !== false ? str_replace('www.', '', $domain) : 'www.'.$domain;
  }
  return $www_domains;
}

// Certbot helper
function wp_cli_letsencrypt_certbot($domains){
  global $certbot_cmd;
  $network_domain = DOMAIN_CURRENT_SITE;
  $email = get_option('admin_email');
  $document_root = ABSPATH;
  if (is_array($domains)) $domains = implode(' -d ', $domains);
  if (!empty($domains)) $domains = '-d ' . $domains;
  $cmd = "$certbot_cmd certonly --non-interactive --allow-subset-of-names --expand --force-renewal --webroot -m $email -w $document_root -d $network_domain $domains";
  return exec($cmd);
}

// CLI command
function wp_cli_letsencrypt_command($args, $assoc_args){
  $domains = wp_cli_letsencrypt_domains($assoc_args);
  wp_cli_letsencrypt_clean();
  $result = wp_cli_letsencrypt_certbot($domains);
  if ($result){
    WP_CLI::success( $result );
  }
}
if (class_exists('WP_CLI')){
  WP_CLI::add_command('letsencrypt', 'wp_cli_letsencrypt_command', array(
    'shortdesc' => 'Generate a SAN SSL certificate for the whole WP network, using Let\'s Encrypt client.',
    'synopsis' => array(
      array(
        'type'     => 'assoc',
        'name'     => 'mapping',
        'description' => 'include domains from wp_domain_mapping (not recommended)',
        'optional' => true,
        'default'  => false,
        'options'  => array( false, true ),
      ),
      array(
        'type'     => 'assoc',
        'name'     => 'subdomains',
        'description' => 'include network subdomains (e.g. website.yournetwork.com)',
        'optional' => true,
        'default'  => false,
        'options'  => array( false, true ),
      ),
      array(
        'type'     => 'assoc',
        'name'     => 'https-only',
        'description' => 'generate only certificates for https websites (lookup in options for each website)',
        'optional' => true,
        'default'  => false,
        'options'  => array( false, true ),
      ),
      array(
        'type'     => 'assoc',
        'name'     => 'with-www',
        'description' => 'include domain with or without www (e.g. yourdomain.com and www.yourdomain.com)',
        'optional' => true,
        'default'  => false,
        'options'  => array( false, true ),
      ),
      array(
        'type'     => 'assoc',
        'name'     => 'with-wildcard',
        'description' => 'include network domain with wildcard (e.g. *.yournetwork.com)',
        'optional' => true,
        'default'  => false,
        'options'  => array( false, true ),
      ),
    ),
  ));
}
