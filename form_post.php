<?php
######################################################################
# Wi-Fi-voucher-o-matic - Wi-Fi voucher manager
# Copyright (C) 2017 Valerio Bozzolan, Ivan Bertotto, ITIS Avogadro
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.If not, see <http://www.gnu.org/licenses/>.
######################################################################

require 'load.php';

Header::spawn('form_post');

if( isset(
	$_POST['user_name'],
	$_POST['user_surname'],
	$_POST['user_uid'],
	$_POST['user_type']
) ) {
	$_POST['user_name']    = luser_input($_POST['user_name'],    32);
	$_POST['user_surname'] = luser_input($_POST['user_surname'], 32);
	$_POST['user_uid']     = luser_input($_POST['user_uid'],     128);
	$_POST['user_type']    = User::filterType( $_POST['user_type'] );

	$type = $_POST['user_type'];

	$existing_user = User::factoryByUID( $_POST['user_uid'] )
		->queryRow();

	if( ! $existing_user ) {

		if( $type === 'ata' || $type === 'menthor' ) {
			if( ! has_permission('register_whatever_mail') ) {
				$email = $_POST['user_uid'];
				$pos = strpos($email, '@itisavogadro.it');

				if( $pos === false ) {
					die("Per favore utilizzare solo indirizzi del dominio itisavogadro.it.");
				}
			}
		}

		insert_row('user', [
			new DBCol('user_name',    $_POST['user_name'],    's'),
			new DBCol('user_surname', $_POST['user_surname'], 's'),
			new DBCol('user_uid',     $_POST['user_uid'],     's'),
			new DBCol('user_type',    $type,                  's')
		] );

		$existing_user = User::factoryByID( last_inserted_ID() )
			->queryRow();

		if( ! $existing_user ) {
			die("Impossibile inserire l'utente causa alieni. Segnalare il problema.");
		}
	}

	// $existing_user

	$voucher_type = Voucher::filterType( $existing_user->get(User::TYPE) );

	$yet_obtained_vouchers = 0;
	$MAX_VOUCHERS          = 1;

	if( $voucher_type === 'menthor' ) {
		$MAX_VOUCHERS = 10;

		$yet_obtained_vouchers = (int) query_value(
			sprintf(
				"SELECT COUNT(*) as count FROM {$T('rel_user_voucher')} WHERE user_ID = %d",
				$existing_user->get(User::ID)
			),
			'count'
		);

		if( $yet_obtained_vouchers > 8 ) {
			die("Hai raggiunto il massimo numero di voucher a tua disposizione.");
		}
	}

	$voucher = query_row(
		sprintf(
			"SELECT voucher_ID, voucher_code FROM {$JOIN('voucher')} WHERE voucher_type = '%s' AND NOT EXISTS (".
				"SELECT * FROM {$JOIN('rel_user_voucher')} WHERE rel_user_voucher.voucher_ID = voucher.voucher_ID ".
			") LIMIT 1",
			esc_sql( $voucher_type )
		),
		'Voucher'
	);

	$yet_obtained_vouchers++;

	if( ! $voucher ) {
		die("Voucher terminati. Contattare Ivan.");
	}

	RelUserVoucher::insertUserVoucher(
		$existing_user->get(User::ID),
		$voucher->get(Voucher::ID)
	);

	// Email generation

	$search = [];
	$sobstitute = [];

	$search[]     = '[CODICE]';
	$sobstitute[] = $voucher->get(Voucher::CODE);

	$search[]     = '[Nome]';
	$sobstitute[] = esc_html( $_POST['user_name'] );

	$search[]     = '[Cognome]';
	$sobstitute[] = esc_html( $_POST['user_surname'] );

	$search[]     = '[numero]';
	$sobstitute[] = $yet_obtained_vouchers;

	$search[]     = '[numeri]';
	$sobstitute[]  = $MAX_VOUCHERS;

	$mail_content = file_get_contents( STATIC_PATH . __ . 'email.html' );
	$mail_content = str_replace( $search, $sobstitute, $mail_content );

	SMTPMail::send(
		$existing_user->get(User::UID),
		_("Il tuo voucher ITI Avogadro"),
		$mail_content
	);

	SMTPMail::send(
		CONTACT_EMAIL,
		sprintf(
			_("Nuovo voucher rilasciato per %s"),
			$existing_user->get(User::UID)
		),
		$mail_content
	);

	$done = true;
}

?>
<section class="mbr-section mbr-section-nopadding" id="video1-l">
	<div class="mbr-figure">
		<div><img src="<?php echo STATIC_ROOT ?>/images/avowifiok-1400x602.png"></div>
		<div class="mbr-figure-caption">
			<div class="container">
				<?php if( $done ): ?>
					<p><h1>Ottimo!</h1></p>
					<p>Leggi la posta.</p>
					Ti abbiamo inviato il codice per iniziare da subito a navigare.
					<br />
					<b>Buona navigazione!</b>
				<?php else: ?>
					<p>Si è verificato un errore.</p>
				<?php endif ?>
	 		</div>
		</div>
	</div>
</section>

<?php
Footer::spawn();
