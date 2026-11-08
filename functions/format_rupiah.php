<?php

function formatRupiah($amount)
{
	return "Rp " . number_format((float) $amount, 0, ",", ".");
}
