<?php

class Model
{
    /**
     * @var {array} $fillable
     */
    protected $fillable = [];

    public function fill($object)
    {
        $_values = get_object_vars($object);
        foreach ($_values as $key => $value) {
            if (!in_array($key, $this->fillable))
                continue;
            $this->{$key} = $value ?? null;
        }

        return $this;
    }
}

class Travel extends Model
{
    public $id;
    public $employeeName;
    public $departure;
    public $destination;
    public $price;
    public $companyId;
    public $createdAt;

    protected $fillable = ['id', 'employeeName', 'departure', 'destination', 'price', 'companyId', 'createdAt'];
}

class Company extends Model
{
    public $id;
    public $name;
    public $cost;
    public $children;
    protected $parentId;
    protected $createdAt;

    protected $fillable = ['id', 'name', 'parentId', 'children', 'cost', 'createdAt'];
}

class APIHelpers
{
    // const API_ROOT = "https://5f27781bf5d27e001612e057.mockapi.io/webprovise";
    // const COMPANIES_API = "/companies";
    // const TRAVELS_API = "/travels";

    const API_ROOT = "http://demo.local";
    const COMPANIES_API = "/companies.json";
    const TRAVELS_API = "/travels.json";

    static function getCompanies()
    {
        return array_map(function ($item) {
            return (new Company())->fill($item);
        }, self::getJson(self::COMPANIES_API));
    }

    static function getTravels()
    {
        return array_map(function ($item) {
            return (new Travel())->fill($item);
        }, self::getJson(self::TRAVELS_API));
    }

    static function getJson($endpoint): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, self::API_ROOT . $endpoint);
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result);
    }
}

class CommonHelpers
{

    /**
     * @description: Access to protected properties;
     * @param $obj
     * @param $prop
     * @return mixed
     * @throws ReflectionException
     */
    static function getKey($obj, $prop)
    {
        $reflection = new \ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

    /**
     * @description: Get children from given parents
     * @param $parents
     * @param $children
     * @param $key
     * @return array
     */
    static function getChildren(&$parents, $children, $key)
    {
        $tree = array();
        if (empty($children)) {
            return $parents;
        }

        foreach ($children as $child) {
            if (isset($parents[$child->id])) {
                $child->children = self::getChildren($parents, $parents[$child->id], $key);
            }
            $tree[] = $child;
        }

        return $tree;
    }

    /**
     * @description: Create tree from given data.
     * @param $items
     * @param int $root
     * @param string $key
     * @return array
     * @throws ReflectionException
     */
    static function createTree($items, $root = 0, $key = 'parentId')
    {
        $parents = array();
        foreach ($items as $item) {
            $parents[self::getKey($item, $key)][] = $item;
        }

        return self::getChildren($parents, $parents[$root], $key);
    }

    /**
     * @description: Combine and group travels by companies.
     * @param $travels
     * @param $companies
     * @return array
     * @throws ReflectionException
     */
    static function groupTravelByCompany($travels, $companies)
    {
        $groupTravels = self::createTree($travels, 0, 'companyId');
        $combined = array_map(function ($company) use ($groupTravels) {
            $children = $groupTravels[$company->id] ?? [];
            $company->children = $children;
            $company->cost = $children ? array_sum(array_column($children, 'price')) : 0;

            return $company;
        }, $companies);

        return self::createTree($combined);
    }
}

class TestScript
{
    public function execute()
    {
        $start = microtime(true);

        try {
            $companies = APIHelpers::getCompanies();
            $travels = APIHelpers::getTravels();

            $groupTravels = CommonHelpers::groupTravelByCompany($travels, $companies);
            echo json_encode($groupTravels);
        } catch (Exception $exception) {
            echo "<pre>" . $exception->getMessage() . "</pre>";
        }

        // echo 'Total time: ' . (microtime(true) - $start);
    }
}

(new TestScript())->execute();
