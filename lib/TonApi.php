<?php

use Olifanton\Interop\Address;

class TonApi
{
    private $apiKey;
    private $testnet;

    public function __construct($apiKey, $testnet = false)
    {
        $this->apiKey = $apiKey;
        $this->testnet = $testnet;
        return $this;
    }

    public function changeToTestnet()
    {
        $this->testnet = true;
    }

    public function changeToMainnet()
    {
        $this->testnet = false;
    }

    private function endpoint($action, $data = [])
    {
        $url = $this->testnet ? "https://testnet.tonapi.io/v2/$action" : "https://tonapi.io/v2/$action";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey]);

        if (count($data) > 0) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close($ch);
        return json_decode($server_output, true);
    }

    /// Connect
    private string $tonconnect = "tonconnect";

    public function payload()
    {
        return $this->endpoint("$this->tonconnect/payload", []);
    }

    public function stateInit($stateInit)
    {
        return $this->endpoint("$this->tonconnect/stateinit", [
            "state_init" => $stateInit
        ]);
    }

    /// Account
    private string $accounts = 'accounts';

    public function accountsNfts($account_id, $collection = '', $limit = 100, $offset = 0, $indirect_ownership = true)
    {
        $params = http_build_query([
            "collection" => $collection,
            "limit" => $limit,
            "offset" => $offset,
            "indirect_ownership" => $indirect_ownership ? "true" : "false"
        ]);
        return $this->endpoint("$this->accounts/$account_id/nfts?$params", []);
    }

    /// NFT
    private string $nft = "nfts";

    public function accountNftHistory($account_id)
    {
        return $this->endpoint("accounts/$account_id/nfts/history", []);
    }

    public function nftsCollections($account_id = '', $limit = 100, $offset = 0)
    {
        return $this->endpoint("$this->nft/collections/$account_id", []);
    }

    public function nftsCollectionsItems($account_id = '', $limit = 100, $offset = 0)
    {
        return $this->endpoint("$this->nft/collections/$account_id/items?limit=$limit&offset=$offset", []);
    }

    public function nfts($account_id = '')
    {
        return $this->endpoint("$this->nft/$account_id", []);
    }

    public function nftsHistory($account_id = '')
    {
        return $this->endpoint("$this->nft/$account_id/history", []);
    }

    public function getTransactions($account_id, $limit = 100, $beforeLT = null, $afterLT = null, $sort_order = null)
    {
        $url = "blockchain/accounts/$account_id/transactions?limit=$limit";
        if (!is_null($beforeLT)) {
            $url .= "&before_lt=$beforeLT";
        }
        if (!is_null($afterLT)) {
            $url .= "&after_lt=$afterLT";
        }
        if (!is_null($sort_order)) {
            $url .= "&sort_order=$sort_order";
        }
        return $this->endpoint($url, []);
    }


    public function getPrice($tokens = 'ton', $currency = 'usd')
    {
        return $this->endpoint("rates?tokens=$tokens&currencies=$currency", []);
    }

    public function getPrices($tokens = ['ton'], $currency = ['usd', 'ton'])
    {
        return $this->endpoint("rates?tokens=" . implode(',', $tokens) . "&currencies=" . implode(",", $currency), []);
    }

    public function runMethod($accountId, $methodName, $args = [])
    {
        $arguments = [];
        foreach ($args as $arg) {
            $arguments[] = 'args=' . $arg;
        }
        $arg = count($arguments) > 0 ? "?" . implode("&", $arguments) : "";
        return $this->endpoint("blockchain/accounts/$accountId/methods/$methodName" . $arg, []);
    }

    public function getJettonInfo($accountId)
    {
        return $this->endpoint("jettons/$accountId", []);
    }

    public function getAccountJettons($accountId, $jettonId)
    {
        return $this->endpoint("accounts/$accountId/jettons/$jettonId", []);
    }

    public function getJettonInfoMigration(mysqli|null $mysqli, $accountId)
    {
        global $sharedDB;
        if ($mysqli === null)
            $mysqli = $sharedDB;

        $accountId = (new Address($accountId))->toString(false, false, false);

        $res = $mysqli->query("SELECT * FROM jettons WHERE master = '$accountId'");
        if ($res->num_rows == 1) {
            $row = $res->fetch_assoc();
            $json = $row['json'];
            return json_decode(base64_decode($json), true);
        } else {
            $data = $this->endpoint("jettons/$accountId", []);
            $json = base64_encode(json_encode($data));

            $jetton = $data['metadata'];
            $jettonLogo = base64_encode($jetton['image']);
            $jettonName = base64_encode($jetton['name']);
            $jettonDescription = base64_encode(!empty($jetton['description']) ? $jetton['description'] : "");
            $jettonSymbol = ($jetton['symbol']);
            $jettonDecimals = (int)($jetton['decimals']);
            $network = $this->testnet ? "TESTNET" : "MAINNET";

            $mysqli->query("INSERT IGNORE INTO jettons (symbol, name, decimals, description, image, master, json, network) VALUE ('$jettonSymbol', '$jettonName', '$jettonDecimals', '$jettonDescription', '$jettonLogo', '$accountId', '$json', '$network')");
            return $data;
        }
    }

    public function getJettonMasterInfoWithWalletAddress(mysqli|null $mysqli, $accountId)
    {
        global $sharedDB;
        if ($mysqli === null)
            $mysqli = $sharedDB;

        if (!Address::isValid($accountId))
            return false;

        $accountId = (new Address($accountId))->toString(false, false, false);
        $res = $mysqli->query("SELECT * FROM jettons_wallet_address WHERE address = '$accountId'");
        if ($res->num_rows == 1) {
            $row = $res->fetch_assoc();
            $master = $row['master'];
        } else {
            $master = $this->getJettonMasterAddressWithWalletAddress($mysqli, $accountId);
        }
        return $this->getJettonInfoMigration($mysqli, $master);
    }

    public function getJettonMasterAddressWithWalletAddress(mysqli|null $mysqli, $accountId)
    {
        global $sharedDB;
        if ($mysqli === null)
            $mysqli = $sharedDB;

        $accountId = (new Address($accountId))->toString(false, false, false);
        $res = $mysqli->query("SELECT * FROM jettons_wallet_address WHERE address = '$accountId'");
        if ($res->num_rows == 1) {
            $row = $res->fetch_assoc();
            return $row['master'];
        } else {
            $res = $this->runMethod($accountId, 'get_wallet_data');
            if ($res['success']) {
                $stack = $res['stack'];
                $masterAddress = \Olifanton\Interop\Boc\Cell::oneFromBoc(loadStackValue($stack, 2), false)->beginParse()->loadAddress()->toString(false, true, false);
                $mysqli->query("INSERT IGNORE INTO jettons_wallet_address (address, master) VALUE ('$accountId', '$masterAddress')");
                return $masterAddress;
            }
        }
        return false;
    }


    function loadStackValue($stack, $index)
    {
        return $stack[$index][$stack[$index]['type']];
    }

    public final function getEvents(string $wallet, bool $initiator = false, bool $subject_only = true, int $limit = 100, int|null $start_date = 0, int|null $end_date = 0): array
    {
        $events = array();
        $next_from = 0;
        do {
            $response = $this->endpoint(
                "accounts/$wallet/events?initiator=" . (($initiator) ? 'true' : 'false') . '&subject_only=' . (($subject_only) ? 'true' : 'false') . "&limit=" . min($limit, 100) . (!is_null(
                    $start_date
                ) ? "&start_date=$start_date" : '') . (!is_null($end_date) ? "&end_date=$end_date" : '') . ($next_from > 0 ? "&before_lt=$next_from" : '')
            );
            if ($response !== false) {
                $next_from = $response['next_from'];
                $response = $response['events'];
                if (count($response) > 0) {
                    foreach ($response as $event) {
                        if ($event['in_progress'] === false && $event['is_scam'] === false) {
                            foreach ($event['actions'] as $action_index => $action) {
                                if ($action['status'] === 'ok') {

                                    if (in_array($action['type'], array('ContractDeploy', 'ChangeDnsRecord', 'DomainRenew', 'SmartContractExec'))) {
                                        continue; // no need to contract deploy actions yet
                                    }

                                    // skip unverified jettons
                                    if ($action['type'] === 'JettonTransfer') {
                                        if ($action[$action['type']]['jetton']['verification'] !== 'whitelist') {
                                            continue; // skip unverified jettons
                                        }
                                    }

                                    if ($action['type'] === 'JettonSwap') {
                                        if (isset($action[$action['type']]['jetton_master_in']) && $action[$action['type']]['jetton_master_in']['verification'] !== 'whitelist') {
                                            continue; // skip unverified jettons
                                        }
                                        if (isset($action[$action['type']]['jetton_master_out']) && $action[$action['type']]['jetton_master_out']['verification'] !== 'whitelist') {
                                            continue; // skip unverified jettons
                                        }
                                        if (isset($action[$action['type']]['router']) && $action[$action['type']]['router']['is_scam'] !== false) {
                                            continue; // skip unverified routers
                                        }
                                    }
                                    // skip unverified jettons

                                    if (in_array(
                                            $action['type'],
                                            array('NftItemTransfer', 'JettonSwap')
                                        ) || (isset($action[$action['type']]['sender']) && $action[$action['type']]['sender']['is_scam'] === false)) {
                                        if (in_array(
                                                $action['type'],
                                                array('JettonBurn', 'JettonSwap')
                                            ) || (isset($action[$action['type']]['recipient']) && $action[$action['type']]['recipient']['is_scam'] === false)) {
                                            if (in_array($action['type'], array('JettonSwap'))) {
                                                if (!isset($action[$action['type']]['user_wallet']) || $action[$action['type']]['user_wallet']['is_scam'] !== false) {
                                                    error_log('meeeeeeeeeeeeeeeeemmmmm');
                                                    continue;
                                                }
                                            }

                                            if ($action['type'] === 'JettonSwap') {
                                                $events[] = array(
                                                    'raw_data' => json_encode($action, JSON_UNESCAPED_UNICODE),
                                                    'action_index' => $action_index,
                                                    'event_id' => $event['event_id'],
                                                    'dex' => $action[$action['type']]['dex'],
                                                    'account' => $event['account']['address'],
                                                    'timestamp' => $event['timestamp'],
                                                    'type' => 'JETTON_SWAP',
                                                    'sender' => $action[$action['type']]['user_wallet']['address'],
                                                    'is_sender_wallet' => $action[$action['type']]['user_wallet']['is_wallet'] ?? true,
                                                    'ton_out' => ($action[$action['type']]['ton_out'] ?? 0) / pow(10, 9),
                                                    'amount_in' => ($action[$action['type']]['amount_in'] > 0) ? ($action[$action['type']]['amount_in'] / pow(
                                                            10,
                                                            $action[$action['type']]['jetton_master_in']['decimals'] ?? 9
                                                        )) : 0,
                                                    'amount_out' => ($action[$action['type']]['amount_out'] > 0) ? ($action[$action['type']]['amount_out'] / pow(
                                                            10,
                                                            $action[$action['type']]['jetton_master_out']['decimals'] ?? 9
                                                        )) : 0,
                                                    'jetton_master_in' => $action[$action['type']]['jetton_master_in']['address'] ?? null,
                                                    'jetton_master_out' => $action[$action['type']]['jetton_master_out']['address'] ?? null,
                                                    'base_transactions' => json_encode($action['base_transactions']),
                                                    'router_address' => $action[$action['type']]['router']['address'] ?? null,
                                                    'lt' => $event['lt'],
                                                );
                                            } else {
                                                $events[] = array(
                                                    'raw_data' => json_encode($action, JSON_UNESCAPED_UNICODE),
                                                    'action_index' => $action_index,
                                                    'event_id' => $event['event_id'],
                                                    'account' => $event['account']['address'],
                                                    'timestamp' => $event['timestamp'],
                                                    'type' => match ($action['type']) {
                                                        'TonTransfer' => 'TON_TRANSFER',
                                                        'JettonTransfer' => 'JETTON_TRANSFER',
                                                        'ContractDeploy' => 'CONTRACT_DEPLOY',
                                                        'NftItemTransfer' => 'NFT_ITEM_TRANSFER',
                                                        'JettonBurn' => 'JETTON_BURN',
                                                        default => 'OTHER'
                                                    },
                                                    'sender' => $action[$action['type']]['sender']['address'] ?? null,
                                                    'is_sender_wallet' => $action[$action['type']]['sender']['is_wallet'] ?? true,
                                                    'recipient' => $action[$action['type']]['recipient']['address'] ?? null,
                                                    'is_recipient_wallet' => $action[$action['type']]['recipient']['is_wallet'] ?? true,
                                                    'amount' => (isset($action[$action['type']]['amount'])) ? ($action[$action['type']]['amount'] / pow(
                                                            10,
                                                            $action[$action['type']]['jetton']['decimals'] ?? 9
                                                        )) : 0,
                                                    'jetton_master' => $action[$action['type']]['jetton']['address'] ?? null,
                                                    'comment' => $action[$action['type']]['comment'] ?? '',
                                                    'base_transactions' => json_encode($action['base_transactions']),
                                                    'item_address' => $action[$action['type']]['image'] ?? null,
                                                    'lt' => $event['lt'],
                                                );
                                            }

                                        }
                                    }
                                }


                            }
                        }
                    }
                }
            }
        } while ($next_from > 0 && (count($events) < $limit));
        return $events;
    }

    public function getJettonHolders($jettonMasterAddress, $limit = 1000, $offset = 0)
    {
        return $this->endpoint("jettons/$jettonMasterAddress/holders?limit=$limit&offset=$offset", []);
    }

    public function getNftItemImageWithMigration(mysqli $mysqli, $accountId, $ownerAddress)
    {
        if (!Address::isValid($accountId) || !Address::isValid($ownerAddress))
            return false;

        $accountId = (new Address($accountId))->toString(false, false, false);
        $ownerAddress = (new Address($ownerAddress))->toString(false, false, false);

        $res = $mysqli->query("SELECT image FROM global_nfts WHERE address = '$accountId' AND owner = '$ownerAddress'");
        if ($res->num_rows == 1) {
            $row = $res->fetch_assoc();
            return $row['image'];
        } else {
            $data = $this->endpoint("nfts/$accountId", []);
            if (isset($data['sale']) || !isset($data['address']) || isset($data['error']))
                return false;

            $address = $data['address'];
            $ownerAddress = $data['owner']['address'];
            $image = urlencode($data['previews'][count($data['previews']) - 1]['url']);
            $collectionAddress = $data['collection']['address'];

            $mysqli->query("INSERT INTO global_nfts (address, collection_address, owner, image) VALUES ('$address', '$collectionAddress', '$ownerAddress', '$image') ON DUPLICATE KEY UPDATE owner = '$ownerAddress', image = '$image', collection_address = '$collectionAddress'");

            return $image;
        }
    }

    public function getNftItem(mysqli $mysqli, $accountId)
    {
        if (!Address::isValid($accountId))
            return false;

        $accountId = (new Address($accountId))->toString(false, false, false);


        $data = $this->endpoint("nfts/$accountId", []);
        if (isset($data['sale']) || !isset($data['address']) || isset($data['error']))
            return false;

        return $data;
    }

    public function getAccount($account_id)
    {
        return $this->endpoint("accounts/$account_id", []);
    }

    public function getTraces($accountId, $afterLt = null, $beforeLt = null, $limit = 100)
    {
        $params = [];
        if ($beforeLt !== null) {
            $params['before_lt'] = $beforeLt;
        }
        if ($afterLt !== null) {
            $params['after_lt'] = $afterLt;
        }
        if ($limit !== null) {
            $params['limit'] = $limit;
        }
        $params = count($params) > 0 ? "?" . http_build_query($params) : "";
        return $this->endpoint("accounts/$accountId/traces" . $params, []);
    }

    public function getTrace($traceId)
    {
        return $this->endpoint("traces/$traceId", []);
    }
}

class TonApiWebHook
{

}