<?php

namespace MediaWiki\Extension\Example;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

use Title;
use WikitextContent;
use WikiPage;

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once("ApiUtil.php");
require_once("Util.php");

/**
 * Extension:DataAccounting Standard Rest API
 */
class APIRead extends SimpleHandler {

    private const VALID_ACTIONS = [ 
        'request_merkle_proof',
        'request_hash'
    ];

    /** @inheritDoc */
    public function run( $action ) {
        $params = $this->getValidatedParams();
        $var1 = $params['var1'];
        $var2 = $params['var2'] ?? null;
        $var3 = $params['var3'] ?? null;
        $var4 = $params['var4'] ?? null;
        switch ( $action ) {
            #request_merkle_proof:expects witness_id and page_verification hash and returns left_leaf,righ_leaf and successor hash to verify the merkle proof node by node, data is retrieved from the witness_merkle_tree db. Note: in some cases there will be multiple replays to this query. In this case it is required to use the depth as a selector to go through the different layers. Depth can be specified via the $depth parameter; 
        case 'request_merkle_proof':
            if ($var1 == null) {
                return "var1 (/witness_event_id) is not specified but expected";                
            }
            if ($var2 === null) {
                return "var2 (page_verification_hash) is not specified but expected";
            }
            //Redeclaration
            $witness_event_id = $var1;
            $page_verification_hash = $var2;
            $depth = $var3;
            $output = requestMerkleProof($witness_event_id, $page_verification_hash, $depth);
            return $output;

            #Expects Revision_ID [Required] Signature[Required], Public Key[Required] and Wallet Address[Required] as inputs; Returns a status for success or failure
               case 'request_hash':
            $rev_id = $var1;
            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbr = $lb->getConnectionRef( DB_REPLICA );

            $res = $dbr->select(
            'page_verification',
            [ 'rev_id','verification_hash' ],
                'rev_id = ' . $rev_id,
            __METHOD__
            );

            $output = '';
            foreach( $res as $row ) {
                $output .= 'I sign the following page verification_hash: [0x' . $row->verification_hash .']';
            }
            return $output;

        default:
            //TODO Return correct error code https://www.mediawiki.org/wiki/API:REST_API/Reference#PHP_3
            return 'ERROR: Invalid action';
        }
    }

    /** @inheritDoc */
    public function needsWriteAccess() {
        return false;
    }

    /** @inheritDoc */
    public function getParamSettings() {
        return [
            'action' => [
                self::PARAM_SOURCE => 'path',
                ParamValidator::PARAM_TYPE => self::VALID_ACTIONS,
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'var1' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'var2' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'var3' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'var4' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false,
            ],
        ];
    }
}

