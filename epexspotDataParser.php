<?php

class EpexspotDataParser
{
    /* @var string $tradingDate **/
    private string $tradingDate;

    /* @var string $deliveryDate **/
    private string $deliveryDate;

    /* @var string $url **/
    private string $url;

    /* @var string $htmlString **/
    private string $htmlString;

    const MARKET_AREA = "DE-LU";

    /* @var string[] $respsone **/
    private array $response = [
        "meta" => [
            "baseload" => null,
            "peakload" => null,
            "tradingDate" => null,
            "deliveryDate" => null,
            "marketArea" => self::MARKET_AREA
        ],
        "data" => null
    ];

    public function run()
    {
        $this->_buildURL();
        $this->_load();
        $this->parseMeta();
        $this->parseTableData();
        $this->_response();
    }

    private function _load(): void
    {
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $html = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);
        $this->htmlString  = $html;
    }

    private function _buildURL(): void
    {
        $this->deliveryDate = date("Y-m-d");
        $this->response["meta"]["deliveryDate"] = $this->deliveryDate;
        $this->tradingDate = date("Y-m-d", strtotime("-1 day"));
        $this->response["meta"]["tradingDate"] = $this->tradingDate;
        $url = self::URL_STRING;
        $url = str_replace("###trading_date###", $this->tradingDate, $url);
        $url = str_replace("###delivery_date###", $this->deliveryDate, $url);
        $url = str_replace("###market_area###", self::MARKET_AREA, $url);
        $this->url = $url;
    }

    private function parseMeta(): void
    {
        if (preg_match_all(self::PATTERN_LOAD, $this->htmlString, $matches, PREG_SET_ORDER)) {
            $values = array();
            foreach ($matches as $match) {
                $type = $match[1];
                $value = str_replace(',', '', $match[2]);
                $values[$type] = $value;
            }

            $this->response["meta"]["baseload"] = $values['Baseload'];
            $this->response["meta"]["peakload"] = $values['Peakload'];
        }
    }

    private function parseTableData(): void
    {
        preg_match_all(self::PATTERN_TABLE, $this->htmlString, $matches, PREG_SET_ORDER);

        $returnArray = array();
        $subArrayBody = [
            "hour" => null,
            "buyVolume" => null,
            "sellVolume" => null,
            "volume" => null,
            "price" => null,
        ];
        $buyVolume = array();
        $sellVolume = array();
        $volume = array();
        $price = array();

        // The commas must be removed.
        // If no further logic happens here, a string will suffice. Otherwise this should be cast to a float
        foreach ($matches as $match) {
            $buyVolume[] = str_replace(',', '', $match[1]);
            $sellVolume[] = str_replace(',', '', $match[2]);
            $volume[] = str_replace(',', '', $match[3]);
            $price[] = str_replace(',', '', $match[4]);
        }

        for ($i = 0; $i < count($buyVolume); $i++) {
            $subArrayBody["hour"] = $i;
            $subArrayBody["buyVolume"] = $buyVolume[$i];
            $subArrayBody["sellVolume"] = $sellVolume[$i];
            $subArrayBody["volume"] = $volume[$i];
            $subArrayBody["price"] = $price[$i];
            $returnArray[] = $subArrayBody;
        }

        $this->response["data"] = $returnArray;
    }

    private function _response()
    {
        $response = json_encode($this->response, JSON_PRETTY_PRINT);
        file_put_contents(time() . ".json", $response);
        echo $response;
    }

    // Hopefully the pattern works forever :D
    const PATTERN_TABLE =  '/<tr[^>]*>\s*<td>([\d,.]+)<\/td>\s*<td>([\d,.]+)<\/td>\s*<td>([\d,.]+)<\/td>\s*<td>([\d,.]+)<\/td>\s*<\/tr>/';
    const PATTERN_LOAD = '/<th>(Baseload|Peakload)<\/th>\s*<th>\s*<div[^>]*>\s*<span>([\d,.]+)<\/span>/';
    const URL_STRING = "https://www.epexspot.com/en/market-data?market_area=###market_area###&trading_date=###trading_date###&delivery_date=###delivery_date###&modality=Auction&sub_modality=DayAhead&product=60&data_mode=table";
}

(new EpexspotDataParser)->run();
