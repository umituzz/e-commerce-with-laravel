<?php

namespace App\Http\Controllers;

use App\Mail\KullaniciKayit;
use App\Models\KullaniciDetay;
use App\Models\SepetUrun;
use App\Models\Sepet;
use App\Models\Kullanici;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Cart;

class KullaniciController extends Controller
{
    public function __construct()
    {
        $this->middleware("guest")->except("oturumukapat");
    }

    public function giris_form()
    {
        return view('kullanici.oturumac');
    }

    public function giris()
    {
        $this->validate(request(),[
            "email"     => "required|email",
            "sifre"     => "required"
        ]);

        if(auth()->attempt(["email" => request("email"),"password" => request("sifre")],request()->has("benihatirla")))
        {
            request()->session()->regenerate();

            $aktif_sepet_id = Sepet::aktif_sepet_id();
            if(is_null($aktif_sepet_id))
            {
                $aktif_sepet = Sepet::create(["kullanici_id" => auth()->id()]);
                $aktif_sepet_id = $aktif_sepet->id;
            }
            session()->put("aktif_sepet_id",$aktif_sepet_id);

            if(Cart::count() > 0)
            {
                foreach(Cart::content() as $cartItem)
                {
                    SepetUrun::updateOrCreate(
                        ["sepet_id" => $aktif_sepet_id, "urun_id" => $cartItem->id],
                        ["adet" => $cartItem->qty, "fiyat" => $cartItem->price, "durum" => "Beklemede"]
                    );
                }
            }
            Cart::destroy();
            $sepetUrunler = SepetUrun::where("sepet_id",$aktif_sepet_id)->get();
            foreach($sepetUrunler as $sepetUrun)
            {
                Cart::add($sepetUrun->urun->id,$sepetUrun->urun->urun_ad,$sepetUrun->adet,$sepetUrun->fiyat,["slug" => $sepetUrun->urun->slug]);
            }

            return redirect()->intended("/");
        }
        else
        {
            $errors = ["email" => "Hatalı Giriş, Bilgilerinizi Kontrol Ediniz"];
            return back()->withErrors($errors);
        }
    }

    public function kaydol_form()
    {
        return view('kullanici.kaydol');
    }

    public function kaydol()
    {
        $this->validate(request(),[
            "adsoyad"   => "required|min:5|max:60",
            "email"     => "required|email|unique:kullanici",
            "sifre"     => "required|confirmed"
        ]);

        $kullanici = Kullanici::create([
            "adsoyad"               => request("adsoyad"),
            "email"                 => request("email"),
            "sifre"                 => Hash::make(request("sifre")),
            "aktivasyon_anahtari"   => Str::random(60),
            "aktif_mi"   => 0,
        ]);

        $kullanici->detay()->save(new KullaniciDetay());

        Mail::to(request("email"))->send(new KullaniciKayit($kullanici));

        auth()->login($kullanici);

        return redirect()->route("anasayfa");
    }

    public function oturumukapat()
    {
        auth()->logout();
        request()->session()->flush();
        request()->session()->regenerate();
        return redirect()->route("anasayfa");

    }

    public function aktiflestir($anahtar)
    {
        $kullanici = Kullanici::where("aktivasyon_anahtari",$anahtar)->first();
        if(!is_null($kullanici))
        {
            $kullanici->aktivasyon_anahtari = NULL;
            $kullanici->aktif_mi            = 1;
            $kullanici->save();

            return redirect()
                ->to("/")
                ->with("mesaj","Kullanıcı Kaydınız Aktifleştirildi")
                ->with("mesaj_tur","success");
        }
        else
        {
            return redirect()
                ->to("/")
                ->with("mesaj","Kullanıcı Kaydınız Aktifleştilemedii")
                ->with("mesaj_tur","warning");
        }
    }

}