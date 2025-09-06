// Kurdish UI runtime translator (safety net)
(function(){
  var map = {"Save": "سەیڤکردن", "Submit": "ناردن", "Update": "نوێکردنەوە", "Edit": "دەستکاری", "Delete": "سڕینەوە", "Remove": "لابردن", "Cancel": "پاشگەزبونەوە", "Close": "داخستن", "Back": "گەڕانەوە", "View": "بینین", "Print": "چاپ", "Download": "داگرتن", "Search": "گەڕان", "Filter": "فیلتەرکردن", "Reset": "نوێ کردنەوە", "Actions": "کردارەکان", "Action": "کردار", "Yes": "بەڵێ", "No": "نەخێر",
     "OK": "باشە", "Are you sure?": "دڵنیای؟", "Confirm": "دڵنیابوونەوە", "Products": "کالاكان", "Product": "کالا", "Customers": "كڕیاران", "Customer": "كڕیار", "Suppliers": "فرۆشیاران", "Supplier": "فرۆشیار",
      "Receipts": "وەسڵەکان", "Receipt": "وەسڵ", "Sales": "فرۆشتنەکان", "Sale": "فرۆشتن", "Purchases": "کڕین", "Purchase": "کڕین", "Dashboard": "داشبۆرد",
       "Reports": "راپۆرت", "ID": "ژ.", "Name": "ناو", "Title": "ناونیشان", "Description": "دەربارە", "Notes": "تێبینی", "Note": "تێبینی", "Phone": "ژمارە مۆبایل",
        "Email": "ئیمەیل", "Address": "ناونیشان", "Date": "ڕێکەوت", "Time": "کات", "Status": "دۆخ", "Created At": "دروستکراوە لە",
         "Updated At": "نوێکراوەتەوە لە", "Options": "هەڵبژاردەکان", "Price": "نرخ", "Buy Price": "نرخی کڕین", "Purchase Price": "نرخی کڕین",
          "Sell Price": "نرخی فرۆشتن", "Sale Price": "نرخی فرۆشتن", "Unit Price": "نرخی یەکە", "Quantity": "ژمارە", "Qty": "ژمارە",
           "Amount": "بڕ", "Paid": "پارەدراوە", "Credit": "قەرز", "Balance": "ماوە/قەرز", "Total": "کۆی گشتی", "Sub Total": "کۆی بۆماوە",
            "Subtotal": "کۆی بۆماوە", "Grand Total": "کۆی گشتی", "Discount": "داشكان", "Tax": "باژ", "Payment": "پارەدان",
             "Payments": "پارەدانەکان", "Method": "ڕێگا", "Cash": "نەقد", "Card": "کارت",
              "New Sale": "فرۆشتنی نوێ", "New Purchase": "کڕینی نوێ", "Receipt Items": "کاڵاکانی وەسڵ", "Edit Receipt": "دەستکاری کردنی وەسڵ",
               "Add Item": "زیادکردنی کاڵا", "Remove Item": "لابردنی کاڵا", "Unit": "یەکە", "Home": "سەرەکی", "Settings": "ڕێکخستن",
                "Logout": "چوونەدەرەوە", "Login": "چوونەژوورەوە"};
  function run(){
    var nodes = document.querySelectorAll('button, a, th, td, label, span, h1, h2, h3, h4, h5, h6, option, small, strong, p, div');
    nodes.forEach(function(n){
      if(n.children && n.children.length>0) return;
      var t = n.textContent && n.textContent.trim();
      if(!t) return;
      if(map[t]){
        n.textContent = n.textContent.replace(t, map[t]);
        return;
      }
      Object.keys(map).forEach(function(k){
        var re = new RegExp('(^|\b)'+k.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'(\b|$)','g');
        if(re.test(n.textContent)){
          n.textContent = n.textContent.replace(re, map[k]);
        }
      });
    });
  }
  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',run);} else {run();}
})();