<?php

function sendResponse($smsMessage) {
    $reply = rawurlencode($smsMessage);
    header("Content-Type: text/html; charset=utf-8");
    header("text: ".$reply);
}



setlocale(LC_CTYPE, 'en_US.UTF-8');
mb_internal_encoding("UTF-8");
mb_http_output('UTF-8');

$phone = $_GET["phone"];
$smscenter = $_GET["smscenter"];
$text_utf8 = rawurldecode($_GET["text"]);
$headers = getallheaders();



    if(empty($phone)){
        $phone=$headers['phone'];
    }

    if(empty($smscenter)){
        $smscenter = $headers['smscenter'];
 }

    if(empty($text_utf8) || strlen($text_utf8) == 0 || $text_utf8 == "") {
        $smsMessage = "Kod poruke nepoznat, za vise informacija posaljite poruku INFO.";
        sendResponse($smsMessage);

    } else {

        $text_utf8 = str_replace(";", "", $text_utf8);
        $explodeovanText = explode(' ',trim($text_utf8));
        $lowerCase = strtolower($explodeovanText[0]);
        switch ($lowerCase) {
            case "info":
                info();
                break;
            case "repertoar":
                knjige();
                break;
            case "knjiga":
                prikaz($explodeovanText[1]);
                break;
            case "kupi":
                kupi($explodeovanText[1], $explodeovanText[2]);
                break;
            case "dodaj":
                izmeni($explodeovanText[1], $explodeovanText[2]);
                break;
            case "otkazi":
                otkazi($explodeovanText[1]);
                break;
            default:
                $smsMessage = "Kod poruke nepoznat, za vise informacija posaljite poruku INFO.";
                sendResponse($smsMessage);
        }
}

function info(){
        $smsMessage = "Za aktuelni repertoar posaljite kod: REPERTOAR. Za knjige kod: KNJIGA ID. Kupite KNJIGU:";
        sendResponse($smsMessage);
}


function knjige(){

    /*
     * if($broj < 0 || is_numeric($broj)==false )
        {
            $smsMessage = "Poruka poslata sa nepoznatim kodom";
        } else {
        $smsMessage = '';
    */
            $DBConnect = connect();
            if ($DBConnect->connect_error) {
                $smsMessage = "Ne moze se uspostaviti konekcija sa bazom, pokusajte kasnije.";
            } else {
                $repertoar = "SELECT * FROM knjige"; //WHERE datum > CURRENT_TIME()
                $result = mysqli_query($DBConnect, $repertoar);
                    if($result) {
                        if(mysqli_num_rows($result) !== 0) {
                            $smsMessage = "Knjige: ";
                                while($red = mysqli_fetch_array($result)) {
                                        $smsMessage .= "(".$red["naziv"].",ID: ".$red["id_knjige"].", Cena: ".$red["cena"]." RSD)\n";
                                    }
                                                            } else { $smsMessage = "Trenutno nema knjige na stanju!"; }
                                }
                    }

        disconnect($DBConnect);
        sendResponse($smsMessage);

}


function prikaz($id){
    $DBConnect = connect();
    if ($DBConnect->connect_error) {
        $smsMessage .= "Ne moze se uspostaviti konekcija sa bazom, pokusajte kasnije.";
    } else {
        $sveKnjige = "SELECT knjige.naziv, knjige.cena, knjiga.id";
        $result = mysqli_query($DBConnect, $sveKnjige);
        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $smsMessage = "";
                while ($red = mysqli_fetch_array($result)) {
                    $smsMessage .= "(ID: ".$red["id_knjige"]." ".$red["naziv"].",".$red["cena"]."RSD. datum: ".$red["datum"].")";
                }
            }
        }



    }
    disconnect($DBConnect);
    sendResponse($smsMessage);
}


function kupi($id, $brKnjiga){
    if($id < 0 || $brKnjiga < 0 || is_numeric($id)==false || is_numeric($brKnjiga)==false) {
        $smsMessage = "Poslali ste poruku u pogresnom formatu";

    } else {
        $maxBrKnjiga = 1;
        $telefon=$_REQUEST["phone"];
        $smsMessage = "Greska!";
        $DBConnect = connect();
        if ($DBConnect->connect_error) {
            $smsMessage = "Ne moze se uspostaviti konekcija sa bazom, pokusajte kasnije.";
        } else {
            $kapacitetQuery = "SELECT kapacitet FROM knjiga WHERE id_knjige = $id";
            $resultKapacitet = mysqli_query($DBConnect, $kapacitetQuery);
            while ($red = mysqli_fetch_array($resultKapacitet)) {
                $kapacitet = $red["kapacitet"];
            }

            $isIDValid = "SELECT id_knjige FROM knjiga WHERE id_knjige = $id";
            $resultValidID = mysqli_query($DBConnect, $isIDValid);


            $cenaQuery = "SELECT cena FROM `repertoar` 
                                   LEFT JOIN knjiga ON repertoar.id_repertoar = knjiga.id_repertoar 
                                   WHERE id_knjige = $id";
            $resultCena = mysqli_query($DBConnect, $cenaQuery);
            while ($red = mysqli_fetch_array($resultCena)) {
                $cena = $red["cena"];
            }

            /*
             *  Validacija
             */
            if (mysqli_num_rows($resultValidID) == 0) {
                $smsMessage = "Ne postoji ID broj za ovu knjigu, molimo Vas posaljite tacan ID knjige koju zelite da kupite.";
            } elseif ($kapacitet == 0) {
                $smsMessage = "Nazalost ova knjiga je rezervisana";
            } elseif ($kapacitet < $brKnjiga) {
                $smsMessage = "Ostalo je samo jos $kapacitet knjiga koje mozete kupiti.";
            } elseif ($DBConnect->connect_error) {
                $smsMessage = "Ne moze se uspostaviti konekcija sa bazom, pokusajte kasnije.";
            } elseif ($brKnjiga> $maxBrKnjiga) {
                $smsMessage = "Ne mozete rezervisati vise od 1 knjige!";
            } else {
                $query = "SELECT id_knjige, br_tel FROM rezervacija WHERE id_knjige = $id AND br_tel = $telefon";
                $rezultat = mysqli_query($DBConnect, $query);

                if (mysqli_num_rows($rezultat) == 0) {
                    $rezKnjigu = "INSERT INTO rezervacija (id_knjige) VALUES ($id, $brKnjiga, $telefon)";
                    $resultRezervacije = mysqli_query($DBConnect, $rezKnjigu);

                    if ($resultRezervacije) {
                        $updateKapacitet = "UPDATE knjiga SET kapacitet = kapacitet - $brKnjiga WHERE id_knjige = $id";
                        $resultUpdatedKapacitet = mysqli_query($DBConnect, $updateKapacitet);
                        if ($resultUpdatedKapacitet) {
                            $smsMessage = "Ukupno kupljenih knjiga: $brKnjiga. \nUkupno uplatiti:" . $cena * $brKnjiga . ". \nDa bi ste otkazali rezervaciju, posaljite: \"OTKAZI " . $id . "\"";
                        } else {
                            $smsMessage = "Greska u bazi, molimo pokusajte kasnije.";
                        }
                    } else {
                        $smsMessage = "Doslo je do greske molimo pokusajte kasnije.";
                    }

                } else {
                    $smsMessage = "Vec ste rezervisali ovu knjigu!";
                }
            }
        }

        disconnect($DBConnect);
        }
        sendResponse($smsMessage);
}




function izmeni($id, $brKnjiga)
{

    if ($id < 0 || $brKnjiga < 0 || is_numeric($id) == false || is_numeric($brKnjiga) == false) {
        $smsMessage = "Poslali ste poruku u pogresnom formatu";
    } else {
        $maxBrKnjiga = 1;
        $telefon = $_REQUEST["phone"];
        $smsMessage = "Greska!";
        $DBConnect = connect();
        $rezervisano = 0;

        if ($DBConnect->connect_error) {
            $smsMessage = "Ne moze se uspostaviti konekcija sa bazom, pokusajte kasnije.";
        } else {
            $isIDValid = "SELECT id_knjige FROM knjiga WHERE id_knjige = $id";
            $resultValidID = mysqli_query($DBConnect, $isIDValid);

            if (mysqli_num_rows($resultValidID) == 0) {
                $smsMessage = "Ne postoji ID broj za ovu knjigu, molimo Vas posaljite tacan ID knjige koju zelite da dopunite.";

            } 

                
                // Update karata
                $noviBrojKnjiga = $rezervisano + $brKnjiga;

                $cenaQuery = "SELECT cena FROM `repertoar` 
                                   LEFT JOIN knjiga ON repertoar.id_repertoar = knjiga.id_repertoar 
                                   WHERE id_knjige = $id";
                $resultCena = mysqli_query($DBConnect, $cenaQuery);

                while ($red = mysqli_fetch_array($resultCena)) {
                    $cena = $red["cena"];
                }

                $kapacitetQuery = "SELECT kapacitet FROM knjiga WHERE id_knjige = $id";
                $resultKapacitet = mysqli_query($DBConnect, $kapacitetQuery);

                while ($red = mysqli_fetch_array($resultKapacitet)) {
                    $kapacitet = $red["kapacitet"];
                }

                if ($kapacitet == 0) {
                    $smsMessage = "Nazalost sva mesta su rezervisana.";
                } elseif ($kapacitet < $brKnjiga) {
                    $smsMessage = "Ostalo je samo jos $kapacitet sedista koje mozete rezervisati.";
                } elseif ($DBConnect->connect_error) {
                    $smsMessage = "Ne moze se uspostaviti konekcija sa bazom, pokusajte kasnije.";
                } elseif ($noviBrojKnjiga > $maxBrKnjiga) {
                    $smsMessage = "Ne mozete rezervisati vise od 1 knjige!";
                } else {
                    $query = "SELECT id_knjige, br_tel FROM rezervacija WHERE id_knjige = $id AND br_tel = $telefon";
                    $rezultat = mysqli_query($DBConnect, $query);

                    if (mysqli_num_rows($rezultat) == 1) {
                        $queryUpdate = "UPDATE rezervacija SET br_rez_mesta = $noviBrojKnjiga WHERE id_knjige = $id AND br_tel = $telefon;";
                        $resultUpdate = mysqli_query($DBConnect, $queryUpdate);

                        if ($resultUpdate) {
                            $kapacitetUpdate = "UPDATE knjiga SET kapacitet = kapacitet - $brKnjiga WHERE id_knjige = $id";
                            $resultKapacitetUpdate = mysqli_query($DBConnect, $kapacitetUpdate);

                            if ($resultKapacitetUpdate) {
                                $smsMessage = "Karata rezervisano: $noviBrojKarata. \nUkupno uplatiti:" . $cena * $noviBrojKarata . ".\nDa bi ste otkazali rezervaciju, posaljite: \"OTKAZI " . $id . "\"";
                            } else {
                                $smsMessage = "Greska u bazi, molimo pokusajte kasnije.";
                            }
                        }
                    }
                }
            }


        }
        disconnect($DBConnect);
    }
    sendResponse($smsMessage);



function otkazi($id)
{
    if ($id < 0 || is_numeric($id) == false) {
        $smsMessage = "Poslali ste poruku u pogresnom formatu";
    } else {
        $telefon = $_REQUEST["phone"];;
        $rezervisano = 0;
        $smsMessage = "Greska";
        $DBConnect = connect();
        if ($DBConnect->connect_error) {
            $smsMessage = "Ne moze se uspostaviti konekcija sa bazom, pokusajte kasnije.";
        } else {
            $query = "SELECT * FROM rezervacija WHERE id_knjige = $id AND br_tel = $telefon";
            $rezultat = mysqli_query($DBConnect, $query);

            if ($rezultat) {
                while ($red = mysqli_fetch_array($rezultat)) {
                    $rezervisano = $red["br_rez_mesta"];
                }
                if (mysqli_num_rows($rezultat) == 1) {
                    // Update kapacitet
                    $queryKapacitet = "UPDATE knjiga SET kapacitet = kapacitet + $rezervisano WHERE id_knjige = $id";
                    $kapacitetUpdate = mysqli_query($DBConnect, $queryKapacitet);
                } else {
                    $smsMessage = 'Greska sa konekcijom, molimo vas pokusajte kasnije. Error: 315';
                }

                if ($kapacitetUpdate) {
                    $queryDelete = "DELETE FROM rezervacija WHERE id_knjige = $id AND br_tel = $telefon;";
                    $resultDelete = mysqli_query($DBConnect, $queryDelete);
                    if ($resultDelete) {
                        $smsMessage = "Uspesno ste otkazali rezervaciju! \nID broj knjige koju ste otkazali: $id. Za kupovinu drugih knjiga posaljite poruku INFO";
                    }
                }
            } else $smsMessage = "Nemate kupljenih knjiga, posaljite INFO za vise informacija";
        }
    }
    disconnect($DBConnect);
    sendResponse($smsMessage);

}


function connect(){
    $DB_serverName="localhost"; //localhost ce uglavnom biti uvek
    $DB_username="id2932792_nikolagavr";
    $DB_password="Mitebalije31";
    $DB_database="id2932792_sms_gateway";
    $DBConnect = mysqli_connect($DB_serverName,$DB_username,$DB_password);
    $baza = mysqli_select_db($DBConnect,$DB_database);
    mysqli_set_charset($DBConnect,'utf8');
    return $DBConnect;
}

function disconnect($DBConnect){
    mysqli_close($DBConnect);
}

