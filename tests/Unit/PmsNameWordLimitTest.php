<?php
namespace Tests\Unit;

use App\Modules\Pms\Rules\NameWordLimit;
use Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class PmsNameWordLimitTest extends TestCase
{
    public function test_names_are_limited_without_blocking_unchanged_existing_names(): void
    {
        $twelve=implode(' ',range(1,12));$long=$twelve.' thirteen';
        $this->assertTrue(Validator::make(['name'=>$twelve],['name'=>new NameWordLimit])->passes());
        $this->assertFalse(Validator::make(['name'=>$long],['name'=>new NameWordLimit])->passes());
        $this->assertTrue(Validator::make(['name'=>$long],['name'=>new NameWordLimit($long)])->passes());
        $this->assertFalse(Validator::make(['name'=>$long.' changed'],['name'=>new NameWordLimit($long)])->passes());
        foreach(['Skyinfinit','Cruxlo','Wecoma','China bedding store','Sales Office - MUL'] as $name){
            $this->assertTrue(Validator::make(['name'=>$name],['name'=>new NameWordLimit])->passes());
        }
    }
}
