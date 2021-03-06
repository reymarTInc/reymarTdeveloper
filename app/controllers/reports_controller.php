<?php
class ReportsController extends AppController {

	var $name = 'Reports';

	var $uses = array('Storage','Branch','Model','Type','Account','User','Item','Brand');

	function inventory_inventory_reports(){
		
		$branchId = $this->data['branch_id'];
		$startDate = $this->data['start_date']." 00:00:00";
		$endDate = $this->data['end_date']." 23:59:59";

		$data = array();
		$thisModelOnly = array();
		
		$prevQuery = "
		select * from (
			select 
				b.model_id,b.status,count( distinct(b.serial_no) ) as total
			 from 
			 	
				 items b 
				join models c on c.id = b.model_id 
				join branches d on d.id = b.started_branch_id
			where 
				( b.started_branch_id = '{$branchId}' and b.entry_datetime < '{$endDate}' and b.status in (1,3))
				or
				( b.started_branch_id = '{$branchId}' and b.status = 5)
			group by b.model_id, b.status
		)Reports
		";		
		
		$prevStock = $this->Model->query($prevQuery);

		foreach ($prevStock as $tmpkey => $tmpvalue) {
			if($tmpvalue['Reports']['status'] == 1 || $tmpvalue['Reports']['status'] == 3)
				$data['prevStock'] [ $tmpvalue['Reports']['model_id'] ] ['total']+= $tmpvalue['Reports']['total'];
			if($tmpvalue['Reports']['status'] == 5)
				$data['repoStock'] [ $tmpvalue['Reports']['model_id'] ] ['total']= $tmpvalue['Reports']['total'];

			$thisModelOnly[ $tmpvalue['Reports']['model_id'] ] = $tmpvalue['Reports']['model_id'] ;
		}

		$deliveryQuery = "
		select * from (
			select 
				b.model_id,a.from_branch_id as branch_id,count( distinct(b.serial_no) ) as total
			 from 
				receiving_transaction_details a 
				join receiving_transactions aa on a.receiving_transaction_id = aa.id
				join items b on a.serial_no = b.serial_no 
				join models c on c.id = b.model_id 
				join branches d on d.id = a.to_branch_id
			where 
				a.confirmed = 1 and a.to_branch_id = '{$branchId}'
				and aa.type = 1 and aa.receiving_datetime >= '{$startDate}' and aa.receiving_datetime <= '{$endDate}'
			group by b.model_id,a.from_branch_id
		)Reports
		";

		$deliveryStock = $this->Model->query($deliveryQuery);

		foreach ($deliveryStock as $tmpkey => $tmpvalue) {
			$data['deliveryStock'] [ $tmpvalue['Reports']['model_id'] ] ['total']= $tmpvalue['Reports']['total'];
			$thisModelOnly[ $tmpvalue['Reports']['model_id'] ] = $tmpvalue['Reports']['model_id'] ;
		}

		$stockInFromBranchQuery = "
		select * from (
			select 
				b.model_id,a.from_branch_id as branch_id,count( distinct(b.serial_no) ) as total
			 from 
				receiving_transaction_details a 
				join receiving_transactions aa on a.receiving_transaction_id = aa.id
				join items b on a.serial_no = b.serial_no 
				join models c on c.id = b.model_id 
				join branches d on d.id = a.to_branch_id
			where 
				a.confirmed = 1 and a.to_branch_id = '{$branchId}'
				and aa.type = 2 and aa.receiving_datetime >= '{$startDate}' and aa.receiving_datetime <= '{$endDate}'
			group by b.model_id,a.from_branch_id
		)Reports
		";



		$stockInFromBranch = $this->Model->query($stockInFromBranchQuery);

		foreach ($stockInFromBranch as $tmpkey => $tmpvalue) {
			$data['stockInFromBranch'] [ $tmpvalue['Reports']['model_id'] ] [ $tmpvalue['Reports']['branch_id'] ]['total'] = $tmpvalue['Reports']['total'];
			$thisModelOnly[ $tmpvalue['Reports']['model_id'] ] = $tmpvalue['Reports']['model_id'] ;
		}

		$stockInToCustomerQuery = "			
		select * from (
			select 
				b.collection_type_id,model_id,b.delivery_datetime,count( distinct(c.serial_no) ) as total
			from 
				sold_transaction_details a 
			    join sold_transactions b on a.sold_transaction_id = b.id
			    join items c on c.serial_no = a.serial_no
			    join models d on d.id = c.model_id
			where
				b.cancel = 0 and b.delivery_datetime >= '{$startDate}' and b.delivery_datetime <= '{$endDate}'
				and a.from_branch_id = '{$branchId}'
			group by model_id,collection_type_id
		)Reports
			";
			
		$stockInToCustomer = $this->Model->query($stockInToCustomerQuery);

		foreach ($stockInToCustomer as $tmpkey => $tmpvalue) {
			$data['stockInToCustomer'] [ $tmpvalue['Reports']['model_id'] ]  [ $tmpvalue['Reports']['collection_type_id'] ]= $tmpvalue['Reports']['total']; 
			$thisModelOnly[ $tmpvalue['Reports']['model_id'] ] = $tmpvalue['Reports']['model_id'] ;
		}

		$stockInToBranchQuery = "
		select * from (
			select 
				b.model_id,aa.to_branch_id as branch_id,count( distinct(b.serial_no) ) as total
			 from 
				stock_transfer_transaction_details a join
			    items b on a.serial_no = b.serial_no join
				models c on c.id = b.model_id join
			    stock_transfer_transactions aa on aa.id = a.stock_transfer_transaction_id join
				branches d on d.id = aa.to_branch_id 
			    
			where 
				a.confirm = 1 and aa.from_branch_id = '{$branchId}'
				and aa.stock_transfer_datetime >= '{$startDate}' and aa.stock_transfer_datetime <= '{$endDate}'
			group by b.model_id,aa.to_branch_id
		)Reports
		";
		$stockInToBranch = $this->Model->query($stockInToBranchQuery);

		
		foreach ($stockInToBranch as $tmpkey => $tmpvalue) {
			$data['stockInToBranch'] [ $tmpvalue['Reports']['model_id'] ] [ $tmpvalue['Reports']['branch_id'] ] = $tmpvalue['Reports']['total'];
			$thisModelOnly[ $tmpvalue['Reports']['model_id'] ] = $tmpvalue['Reports']['model_id'] ;
		}

		
		$this->set('models',$this->Model->find('list',array('conditions'=>array('enabled'=>true),'fields'=>array('id','name'))));
		$this->set('types',$this->Type->find('list'));		
		$branch_ids = $this->Branch->find('list',array('fields'=>array('id','code'),'conditions'=>array('enabled'=>true)));
		$branch_idNs = $this->Branch->find('list',array('fields'=>array('id','name'),'conditions'=>array('enabled'=>true)));
		$this->set('branches',$branch_ids);
		$this->set('accounts',$this->Account->find('list'));

		$this->set('filterBranches',$branch_idNs);

		$this->set('data',$data);
		$this->set('thisModelOnly',$thisModelOnly);

		if(isset($this->data['print'])){
			$this->layout='pdf';
			$this->render('inventory_inventory_reports_print');
		}
	}

	function inventory_inventory_report_details(){
		
		$branchId = $this->data['branch_id'];
		$startDate = $this->data['start_date']." 00:00:00";
		$endDate = $this->data['end_date']." 23:59:59";

		$data = array();
		$thisModelOnly = array();
		$Serials = array();
		// Recieved from Supplier
		$query1 ="
		select * from (
			select 
				a.receiving_datetime,a.reference_no,a.receiving_report_no,c.company,e.name,d.serial_no,d.net_price
			 from 
				receiving_transactions a 
			    join receiving_transaction_details b on a.id = b.receiving_transaction_id 
			    join accounts c on c.id = a.source_account_id
			    join items d on d.serial_no = b.serial_no
			    join models e on e.id = d.model_id
				where a.type = 1 and b.to_branch_id ='{$branchId}' and b.confirmed = 1 
					and a.receiving_datetime >= '{$startDate}' and a.receiving_datetime <= '{$endDate}'
				order by e.name asc
		) Final
		";

		$final1 = $this->Model->query($query1);

		foreach ($final1 as $final1key => $final1value) {
			
			$data['from_supplier'][$final1value['Final']['receiving_report_no']] ['receiving_datetime'] = $final1value['Final']['receiving_datetime'];	
			$data['from_supplier'][$final1value['Final']['receiving_report_no']] ['reference_no'] = $final1value['Final']['reference_no'];	
			$data['from_supplier'][$final1value['Final']['receiving_report_no']] ['receiving_report_no'] = $final1value['Final']['receiving_report_no'];	
			$data['from_supplier'][$final1value['Final']['receiving_report_no']] ['company'] = $final1value['Final']['company'];	
			$data['from_supplier'][$final1value['Final']['receiving_report_no']] ['model'] = $final1value['Final']['name'];	
			$data['from_supplier'][$final1value['Final']['receiving_report_no']] ['Serials'][$final1value['Final']['serial_no']]['serial_no'] = $final1value['Final']['serial_no'];	
			$data['from_supplier'][$final1value['Final']['receiving_report_no']] ['Serials'][$final1value['Final']['serial_no']]['net_price'] = $final1value['Final']['net_price'];	

			$Serials[] = $final1value['Final']['serial_no'];
		}
		// Receive from Branch
		$query2 ="
		select * from (
			select 
				a.receiving_datetime,a.reference_no,a.receiving_report_no,c.name as branch,e.name as model,d.serial_no,d.net_price
			 from 
				receiving_transactions a 
			    join receiving_transaction_details b on a.id = b.receiving_transaction_id 
			    join branches c on c.id = b.from_branch_id
			    join items d on d.serial_no = b.serial_no
			    join models e on e.id = d.model_id
				where a.type = 2 and b.to_branch_id ='{$branchId}' and b.confirmed = 1
					and a.receiving_datetime >= '{$startDate}' and a.receiving_datetime <= '{$endDate}'
				order by e.name asc
		) Final
		";

		$final2 = $this->Model->query($query2);

		foreach ($final2 as $final2key => $final2value) {
			$data['from_branch'][$final2value['Final']['receiving_report_no']] ['receiving_datetime'] = $final2value['Final']['receiving_datetime'];	
			$data['from_branch'][$final2value['Final']['receiving_report_no']] ['reference_no'] = $final2value['Final']['reference_no'];	
			$data['from_branch'][$final2value['Final']['receiving_report_no']] ['receiving_report_no'] = $final2value['Final']['receiving_report_no'];	
			$data['from_branch'][$final2value['Final']['receiving_report_no']] ['branch'] = $final2value['Final']['branch'];	
			$data['from_branch'][$final2value['Final']['receiving_report_no']] ['model'] = $final2value['Final']['model'];	
			$data['from_branch'][$final2value['Final']['receiving_report_no']] ['Serials'][$final2value['Final']['serial_no']]['serial_no'] = $final2value['Final']['serial_no'];	
			$data['from_branch'][$final2value['Final']['receiving_report_no']] ['Serials'][$final2value['Final']['serial_no']]['net_price'] = $final2value['Final']['net_price'];	

			$Serials[] = $final2value['Final']['serial_no'];
		}

		$modelS = $this->Model->find('list');
		
		//from previous
		// $this->Storage->recursive = -1;
		$serial_no=$this->Storage->find('all',array('conditions'=>array(
			'Storage.branch_id'=>$branchId,
			'NOT' => array('Storage.serial_no' => $Serials ) )) );

		
		foreach ($serial_no as $serial_no2key => $serial_no2value) {
			$data['from_previous'][$serial_no2key] ['model'] = $modelS[$serial_no2value['Item']['model_id']];	
			$data['from_previous'][$serial_no2key] ['serial'] = $serial_no2value['Item']['serial_no'];				
			$data['from_previous'][$serial_no2key] ['net'] = $serial_no2value['Item']['net_price'];				
		}

		// Stock Transfer Out
		$query3 ="
		select * from (
			select 
				a.stock_transfer_datetime,a.stock_transfer_no,c.name as branch,e.name as model,d.serial_no,a.type
			from 
				stock_transfer_transactions a 
			    join stock_transfer_transaction_details b on a.id = b.stock_transfer_transaction_id 
			    join branches c on c.id = a.to_branch_id
			    join items d on d.serial_no = b.serial_no
			    join models e on e.id = d.model_id
			where a.type = 1 and a.from_branch_id ='{$branchId}' and b.confirm = 1
				and a.stock_transfer_datetime >= '{$startDate}' and a.stock_transfer_datetime <= '{$endDate}'
				order by e.name asc
		) Final
		";

		$final3 = $this->Model->query($query3);

		$stockOut = array();
		foreach ($final3 as $final3key => $final3value) {
			$stockOut[$final3value['Final']['serial_no']]['stock_datetime'] = $final3value['Final']['stock_transfer_datetime'];
			$stockOut[$final3value['Final']['serial_no']]['stock_transfer_no'] = $final3value['Final']['stock_transfer_no'];
			$stockOut[$final3value['Final']['serial_no']]['branch'] = $final3value['Final']['branch'];
		}

		// Sold
		$query4 ="
		select * from (
			select 
				a.delivery_datetime,a.delivery_receipt_no,concat(c.last_name,', ',c.first_name) as owner,e.name,d.serial_no,e.name as model,d.net_price
		 	from 
				sold_transactions a 
			    join sold_transaction_details b on a.id = b.sold_transaction_id 
			    join accounts c on c.id = a.owner_account_id
			    join items d on d.serial_no = b.serial_no
			    join models e on e.id = d.model_id
				where b.from_branch_id ='{$branchId}' and a.cancel = 0
					and a.delivery_datetime >= '{$startDate}' and a.delivery_datetime <= '{$endDate}'
				order by e.name asc
		) Final
		";

		$final4 = $this->Model->query($query4);


		foreach ($final4 as $final4key => $final4value) {
			$stockOut[$final4value['Final']['serial_no']]['stock_datetime'] = $final4value['Final']['delivery_datetime'];
			$stockOut[$final4value['Final']['serial_no']]['stock_transfer_no'] = $final4value['Final']['delivery_receipt_no'];
			$stockOut[$final4value['Final']['serial_no']]['branch'] = $final4value['Final']['owner'];

			$stockOut[$final4value['Final']['serial_no']]['serial_no'] = $final4value['Final']['serial_no'];
			$stockOut[$final4value['Final']['serial_no']]['model'] = $final4value['Final']['model'];
			$stockOut[$final4value['Final']['serial_no']]['net_price'] = $final4value['Final']['net_price'];
		}

		$this->set('models',$this->Model->find('list',array('conditions'=>array('enabled'=>true),'fields'=>array('id','name'))));
		$this->set('types',$this->Type->find('list'));		
		$branch_ids = $this->Branch->find('list',array('fields'=>array('id','code'),'conditions'=>array('enabled'=>true)));
		$branch_idNs = $this->Branch->find('list',array('fields'=>array('id','name'),'conditions'=>array('enabled'=>true)));
		$this->set('branches',$branch_ids);
		$this->set('accounts',$this->Account->find('list'));

		$this->set('filterBranches',$branch_idNs);

		$this->set('data',$data);
		$this->set('stockOut',$stockOut);
		$this->set('thisModelOnly',$thisModelOnly);

		if(isset($this->data['print'])){
			$this->layout='pdf';
			$this->render('inventory_inventory_report_details_print');
		}
	}
}
?>